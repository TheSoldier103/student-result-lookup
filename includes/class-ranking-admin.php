<?php
if (!defined('ABSPATH')) exit;

class SRL_Ranking_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_menu() {
        add_menu_page(
            'Student Results',
            'Student Results',
            'manage_options',
            'srl-results',
            [__CLASS__, 'render_page'],
            'dashicons-welcome-learn-more',
            58
        );
    }

    public static function enqueue_assets($hook) {
        if (strpos((string)$hook, 'srl-') === false) return;
        wp_enqueue_style(
            'srl-admin-rankings',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-rankings.css',
            [],
            '1.0.0'
        );
    }

    private static function get_sessions($courses) {
        $sessions = [];
        foreach ((array)$courses as $course) {
            $session = trim((string)($course['session'] ?? ''));
            if ($session !== '' && $session !== 'N/A') $sessions[$session] = $session;
        }
        rsort($sessions, SORT_NATURAL);
        return array_values($sessions);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;

        $endpoint = srl_moodle_endpoint();
        $token = srl_moodle_token();

        if (empty($token)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Moodle API token is not configured.</p></div></div>';
            return;
        }

        $all_third = SRL_Moodle_API::fetch_third_term_courses($endpoint, $token);
        $sessions = self::get_sessions($all_third);

        $selected_session = sanitize_text_field($_REQUEST['srl_session'] ?? ($sessions[0] ?? ''));
        $selected_course = absint($_REQUEST['srl_course_id'] ?? 0);

        $courses = array_values(array_filter($all_third, function($course) use ($selected_session) {
            return $selected_session === ''
                || strcasecmp((string)($course['session'] ?? ''), $selected_session) === 0;
        }));

        // Course lookup lets the stored ranking rows display friendly Moodle names.
        $course_lookup = [];
        foreach ((array)$all_third as $course) {
            $course_lookup[(int)$course['id']] = $course;
        }

        $message = '';
        $error = '';

        if (isset($_POST['srl_calculate_rankings'])) {
            check_admin_referer('srl_calculate_rankings');

            $selected_session = sanitize_text_field($_POST['srl_session'] ?? '');
            $selected_course = absint($_POST['srl_course_id'] ?? 0);

            try {
                $result = self::calculate_course_rankings($selected_course, $selected_session);
                $message = sprintf(
                    'Rankings updated successfully for %d students.',
                    (int)$result['count']
                );
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $calculated_courses = SRL_Ranking_Repository::get_calculated_courses($selected_session);
        $preview = $selected_course
            ? SRL_Ranking_Repository::get_course_rankings($selected_course)
            : [];
        $meta = $selected_course
            ? SRL_Ranking_Repository::get_course_meta($selected_course)
            : null;

        echo '<div class="wrap srl-admin-wrap">';
        echo '<h1>3rd Term Rankings</h1>';
        echo '<p>View calculated courses and calculate or update overall 3rd-term positions.</p>';

        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        if ($error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }

        // Session filter shared by the calculated-course list and calculation form.
        echo '<form method="get" style="margin:18px 0;">';
        echo '<input type="hidden" name="page" value="srl-results">';
        echo '<label for="srl_session_filter" style="display:block;margin-bottom:6px;"><strong>Session</strong></label>';
        echo '<select name="srl_session" id="srl_session_filter" onchange="this.form.submit()" style="min-width:260px;">';
        foreach ($sessions as $session) {
            echo '<option value="' . esc_attr($session) . '" '
                . selected($selected_session, $session, false) . '>'
                . esc_html($session)
                . '</option>';
        }
        echo '</select>';
        echo '</form>';

        echo '<h2>Calculated Courses</h2>';

        if ($calculated_courses) {
            echo '<div class="srl-ranking-table-wrap">';
            echo '<table class="widefat striped srl-ranking-table">';
            echo '<thead><tr>
                <th>Course</th>
                <th>Students Ranked</th>
                <th>Last Calculated</th>
                <th>Actions</th>
            </tr></thead><tbody>';

            foreach ($calculated_courses as $row) {
                $course_id = (int)$row['course_id'];
                $course = $course_lookup[$course_id] ?? null;

                $course_name = $course
                    ? trim(($course['class'] ?? '') . ' — ' . ($course['fullname'] ?? ''))
                    : 'Course #' . $course_id;

                $view_url = add_query_arg([
                    'page' => 'srl-results',
                    'srl_session' => $selected_session,
                    'srl_course_id' => $course_id,
                ], admin_url('admin.php'));

                echo '<tr>';
                echo '<td><strong>' . esc_html($course_name) . '</strong></td>';
                echo '<td>' . (int)$row['student_count'] . '</td>';
                echo '<td>' . esc_html($row['calculated_at']) . '</td>';
                echo '<td><a class="button button-secondary" href="' . esc_url($view_url) . '">View Rankings</a></td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        } else {
            echo '<p>No rankings have been calculated for this session yet.</p>';
        }

        echo '<hr style="margin:28px 0;">';
        echo '<h2>Calculate Rankings</h2>';
        echo '<p>Select a 3rd-term course to calculate its rankings. Selecting an already calculated course will update its stored rankings.</p>';

        echo '<form method="post" class="srl-ranking-form">';
        wp_nonce_field('srl_calculate_rankings');

        echo '<input type="hidden" name="srl_session" value="' . esc_attr($selected_session) . '">';

        echo '<div class="srl-admin-field">';
        echo '<label for="srl_course_id"><strong>3rd Term Course</strong></label>';
        echo '<select name="srl_course_id" id="srl_course_id" required>';
        echo '<option value="">Select a course</option>';

        foreach ($courses as $course) {
            echo '<option value="' . (int)$course['id'] . '" '
                . selected($selected_course, (int)$course['id'], false) . '>'
                . esc_html(($course['class'] ?? '') . ' — ' . ($course['fullname'] ?? ''))
                . '</option>';
        }

        echo '</select></div>';

        echo '<div class="srl-admin-actions">';
        submit_button(
            $preview ? 'Recalculate Rankings' : 'Calculate Rankings',
            'primary',
            'srl_calculate_rankings',
            false
        );
        echo '</div>';
        echo '</form>';

        if ($meta && $selected_course) {
            echo '<div class="srl-ranking-meta">';
            echo '<strong>Last calculated:</strong> ' . esc_html($meta['calculated_at']) . ' &nbsp; ';
            echo '<strong>Students ranked:</strong> ' . (int)$meta['student_count'];
            echo '</div>';
        }

        if ($preview) {
            echo '<h2>Ranking Preview</h2>';
            echo '<div class="srl-ranking-table-wrap"><table class="widefat striped srl-ranking-table">';
            echo '<thead><tr>
                <th>Position</th>
                <th>Student</th>
                <th>Total Obtained</th>
                <th>Total Obtainable</th>
                <th>Percentage</th>
            </tr></thead><tbody>';

            foreach ($preview as $row) {
                echo '<tr>';
                echo '<td><strong>' . esc_html(SRL_Grade_Organizer::format_position($row['position_num'])) . '</strong></td>';
                echo '<td>' . esc_html($row['student_name']) . '</td>';
                echo '<td>' . esc_html(number_format((float)$row['total_obtained'], 2)) . '</td>';
                echo '<td>' . esc_html(number_format((float)$row['total_obtainable'], 0)) . '</td>';
                echo '<td>' . esc_html(number_format((float)$row['percentage'], 2)) . '%</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div>';
    }

    public static function calculate_course_rankings($course_id, $session) {
        if (!$course_id) throw new Exception('Please select a course.');

        $endpoint = srl_moodle_endpoint();
        $token = srl_moodle_token();

        $students = SRL_Moodle_API::fetch_enrolled_students($endpoint, $token, $course_id);
        if (!is_array($students)) throw new Exception('Unable to fetch enrolled students from Moodle.');
        if (empty($students)) throw new Exception('No enrolled students were found in this course.');

        $rows = [];

        foreach ($students as $student) {
            $grades = SRL_Moodle_API::fetch_grades($endpoint, $token, $student['id'], $course_id);
            if (!$grades || empty($grades['usergrades'][0]['gradeitems'])) continue;

            $organized = SRL_Grade_Organizer::organize_grade_items($grades['usergrades'][0]['gradeitems']);
            $summary = SRL_Grade_Organizer::calc_third_term_summary($organized['subjects']);

            if (!is_numeric(str_replace(',', '', (string)$summary['obtained']))) continue;
            if (!is_numeric(str_replace(',', '', (string)$summary['obtainable']))) continue;

            $obtained = (float)str_replace(',', '', $summary['obtained']);
            $obtainable = (float)str_replace(',', '', $summary['obtainable']);
            if ($obtainable <= 0) continue;

            $rows[] = [
                'course_id' => $course_id,
                'student_id' => (int)$student['id'],
                'student_name' => (string)$student['fullname'],
                'term' => '3rd Term',
                'session' => $session,
                'total_obtained' => $obtained,
                'total_obtainable' => $obtainable,
                'percentage' => ($obtained / $obtainable) * 100,
            ];
        }

        if (empty($rows)) throw new Exception('No valid 3rd-term results were found for this course.');

        usort($rows, function($a, $b) {
            $cmp = $b['percentage'] <=> $a['percentage'];
            if ($cmp !== 0) return $cmp;

            $cmp = $b['total_obtained'] <=> $a['total_obtained'];
            if ($cmp !== 0) return $cmp;

            return strcasecmp($a['student_name'], $b['student_name']);
        });

        $previous_percentage = null;
        $previous_obtained = null;
        $current_position = 0;
        $ranked_count = count($rows);
        $calculated_at = current_time('mysql');

        foreach ($rows as $index => &$row) {
            $is_tie = $previous_percentage !== null
                && abs($row['percentage'] - $previous_percentage) < 0.00001
                && abs($row['total_obtained'] - $previous_obtained) < 0.00001;

            if (!$is_tie) $current_position = $index + 1;

            $row['position_num'] = $current_position;
            $row['num_students'] = $ranked_count;
            $row['calculated_at'] = $calculated_at;

            $previous_percentage = $row['percentage'];
            $previous_obtained = $row['total_obtained'];
        }
        unset($row);

        SRL_Ranking_Repository::delete_course($course_id);
        SRL_Ranking_Repository::upsert_many($rows);

        return ['count' => count($rows), 'rows' => $rows];
    }
}

SRL_Ranking_Admin::init();
