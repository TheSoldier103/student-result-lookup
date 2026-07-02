<?php
/**
 * Moodle API access layer for Student Result Lookup.
 */
if (!defined('ABSPATH')) exit;

class SRL_Moodle_API {

    public static function fetch_user_by_idnumber($endpoint, $token, $idnumber) {
        $params = [
            'wstoken'            => $token,
            'wsfunction'         => 'core_user_get_users',
            'moodlewsrestformat' => 'json',
            'criteria[0][key]'   => 'idnumber',
            'criteria[0][value]' => $idnumber,
        ];
        $response = wp_remote_get($endpoint . '?' . http_build_query($params));
        if (is_wp_error($response)) return null;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['users'][0] ?? null;
    }

    public static function fetch_user_courses($endpoint, $token, $user_id) {
        $params = [
            'wstoken'            => $token,
            'wsfunction'         => 'core_enrol_get_users_courses',
            'moodlewsrestformat' => 'json',
            'userid'             => $user_id,
        ];
        $response = wp_remote_get($endpoint . '?' . http_build_query($params));
        if (is_wp_error($response)) return null;
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public static function fetch_grades($endpoint, $token, $student_id, $course_id) {
        $params = [
            'wstoken' => $token,
            'wsfunction' => 'gradereport_user_get_grade_items',
            'moodlewsrestformat' => 'json',
            'userid' => $student_id,
            'courseid' => $course_id,
        ];
        $response = wp_remote_get($endpoint . '?' . http_build_query($params));
        if (is_wp_error($response)) return null;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['usergrades'])) return null;
        return $data;
    }

    public static function fetch_student_details($endpoint, $token, $idnumber) {
        $user = self::fetch_user_by_idnumber($endpoint, $token, $idnumber);
        if (!$user) return ['sex' => 'N/A'];
        $sex = 'N/A';
        if (!empty($user['customfields'])) {
            foreach ($user['customfields'] as $field) {
                if (($field['shortname'] ?? '') === 'Sex') {
                    $sex = $field['value'] ?? 'N/A';
                    break;
                }
            }
        }
        return ['sex' => $sex];
    }

    public static function fetch_course_details($endpoint, $token, $course_id) {
        $params = [
            'wstoken' => $token,
            'wsfunction' => 'core_course_get_courses',
            'moodlewsrestformat' => 'json',
            'options[ids][0]' => $course_id,
        ];
        $response = wp_remote_get($endpoint . '?' . http_build_query($params));
        if (is_wp_error($response)) return ['class' => 'N/A', 'term' => 'N/A', 'session' => 'N/A', 'course_complete' => 0];
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data[0])) return ['class' => 'N/A', 'term' => 'N/A', 'session' => 'N/A', 'course_complete' => 0];
        $course = $data[0];
        $parsed = self::parse_course_info($course['shortname'] ?? '');
        $course_complete = 0;
        if (!empty($course['customfields'])) {
            foreach ($course['customfields'] as $field) {
                if (($field['shortname'] ?? '') === 'course_complete') {
                    $course_complete = (int)($field['valueraw'] ?? 0);
                    break;
                }
            }
        }
        $parsed['course_complete'] = $course_complete;
        return $parsed;
    }

    public static function parse_course_info($course_shortname) {
        $parts = explode('-', $course_shortname);
        return [
            'class' => trim($parts[0] ?? 'N/A'),
            'term' => trim($parts[1] ?? 'N/A'),
            'session' => trim($parts[2] ?? 'N/A'),
        ];
    }

    public static function fetch_announcements($endpoint, $token, $course_id) {
        $forum_params = [
            'wstoken' => $token,
            'wsfunction' => 'mod_forum_get_forums_by_courses',
            'moodlewsrestformat' => 'json',
            'courseids[0]' => $course_id,
        ];
        $forum_response = wp_remote_get($endpoint . '?' . http_build_query($forum_params));
        if (is_wp_error($forum_response)) return [];
        $forums = json_decode(wp_remote_retrieve_body($forum_response), true);
        $forum_id = null;
        foreach ((array)$forums as $forum) {
            if (($forum['type'] ?? '') === 'news' && ($forum['name'] ?? '') === 'Announcements') {
                $forum_id = $forum['id'];
                break;
            }
        }
        if (!$forum_id) return [];
        $discussion_params = [
            'wstoken' => $token,
            'wsfunction' => 'mod_forum_get_forum_discussions',
            'moodlewsrestformat' => 'json',
            'forumid' => $forum_id,
        ];
        $discussion_response = wp_remote_get($endpoint . '?' . http_build_query($discussion_params));
        if (is_wp_error($discussion_response)) return [];
        $data = json_decode(wp_remote_retrieve_body($discussion_response), true);
        if (empty($data['discussions'])) return [];
        $announcements = [
            'days_opened' => null,
            'next_term' => null,
            'fees' => null,
            'general' => null,
        ];
        foreach ($data['discussions'] as $discussion) {
            $subject = strtolower($discussion['subject'] ?? '');
            $message = strip_tags($discussion['message'] ?? '');
            if (stripos($subject, 'days school opened') !== false) {
                $announcements['days_opened'] = $message;
            } elseif (stripos($subject, 'next term') !== false && stripos($subject, 'resumption') !== false) {
                $announcements['next_term'] = $message;
            } elseif (stripos($subject, 'next term') !== false && stripos($subject, 'fees') !== false) {
                $announcements['fees'] = $message;
            } elseif (stripos($subject, 'announcement') !== false) {
                $announcements['general'] = $message;
            }
        }
        return $announcements;
    }

    public static function fetch_staff($endpoint, $token, $course_id) {
        $params = [
            'wstoken' => $token,
            'wsfunction' => 'core_enrol_get_enrolled_users',
            'moodlewsrestformat' => 'json',
            'courseid' => $course_id,
        ];
        $response = wp_remote_get($endpoint . '?' . http_build_query($params));
        if (is_wp_error($response)) return ['teacher' => 'N/A', 'principal' => 'N/A'];
        $users = json_decode(wp_remote_retrieve_body($response), true);
        $staff = ['teacher' => 'N/A', 'principal' => 'N/A'];
        foreach ((array)$users as $user) {
            if (!empty($user['roles'])) {
                foreach ($user['roles'] as $role) {
                    if (($role['shortname'] ?? '') === 'editingteacher') {
                        $staff['teacher'] = $user['fullname'] ?? 'N/A';
                    } elseif (($role['shortname'] ?? '') === 'principal') {
                        $staff['principal'] = $user['fullname'] ?? 'N/A';
                    }
                }
            }
        }
        return $staff;
    }
}

// Backward-compatible wrappers: old function names still work.
function srl_fetch_grades($endpoint, $token, $student_id, $course_id) { return SRL_Moodle_API::fetch_grades($endpoint, $token, $student_id, $course_id); }
function srl_fetch_student_details($endpoint, $token, $idnumber) { return SRL_Moodle_API::fetch_student_details($endpoint, $token, $idnumber); }
function srl_fetch_course_details($endpoint, $token, $course_id) { return SRL_Moodle_API::fetch_course_details($endpoint, $token, $course_id); }
function srl_parse_course_info($course_shortname) { return SRL_Moodle_API::parse_course_info($course_shortname); }
function srl_fetch_announcements($endpoint, $token, $course_id) { return SRL_Moodle_API::fetch_announcements($endpoint, $token, $course_id); }
function srl_fetch_staff($endpoint, $token, $course_id) { return SRL_Moodle_API::fetch_staff($endpoint, $token, $course_id); }
