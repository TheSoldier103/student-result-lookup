<?php
/**
 * Plugin Name: Student Result Lookup
 * Description: Parent-facing form to lookup student courses and grades dynamically from Moodle.
 * Version: 6.0-SPLIT
 * Author: Petra Christian Academy
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-moodle-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-grade-organizer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-web-renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pdf-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ranking-repository.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ranking-admin.php';

function srl_moodle_endpoint() {
    return 'https://learn.petrachristianacademy.com/webservice/rest/server.php';
}

function srl_moodle_token() {
    return defined('MOODLE_API_TOKEN') ? MOODLE_API_TOKEN : '';
}


register_activation_hook(__FILE__, ['SRL_Ranking_Repository', 'install']);

function srl_enqueue_frontend_assets() {
    wp_enqueue_style(
        'srl-frontend',
        plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
        [],
        '6.1.0'
    );
}
add_action('wp_enqueue_scripts', 'srl_enqueue_frontend_assets');

function srl_maybe_install_ranking_table() {
    $version = '1.0.0';
    if (get_option('srl_ranking_db_version') !== $version) {
        SRL_Ranking_Repository::install();
        update_option('srl_ranking_db_version', $version);
    }
}
add_action('plugins_loaded', 'srl_maybe_install_ranking_table');

add_action('init', 'srl_handle_pdf_download');

function srl_handle_pdf_download() {
    if (!isset($_GET['srl_download_pdf']) || !isset($_GET['srl_nonce'])) return;
    if (!wp_verify_nonce($_GET['srl_nonce'], 'srl_pdf_download')) wp_die('Security check failed');

    $student_id = isset($_GET['student_id']) ? sanitize_text_field($_GET['student_id']) : '';
    $course_id  = isset($_GET['course_id']) ? sanitize_text_field($_GET['course_id']) : '';

    if (empty($student_id) || empty($course_id)) wp_die('Missing required parameters.');

    error_reporting(0);
    ini_set('display_errors', 0);
    while (ob_get_level()) ob_end_clean();

    $endpoint = srl_moodle_endpoint();
    $token = srl_moodle_token();
    if (empty($token)) wp_die('Moodle API token not configured.');

    $grades_data = srl_fetch_grades($endpoint, $token, $student_id, $course_id);
    if (!$grades_data || empty($grades_data['usergrades'][0])) wp_die('Unable to fetch grades.');

    $student_data  = srl_fetch_student_details($endpoint, $token, $grades_data['usergrades'][0]['useridnumber']);
    $course_info   = srl_fetch_course_details($endpoint, $token, $course_id);
    $announcements = srl_fetch_announcements($endpoint, $token, $course_id);
    $staff         = srl_fetch_staff($endpoint, $token, $course_id);

    $pdf_generator = new SRL_PDF_Generator();
    $pdf_generator->generate_report_card([
        'grades_data' => $grades_data,
        'student_data' => $student_data,
        'course_info' => $course_info,
        'announcements' => $announcements,
        'staff' => $staff,
    ]);
}

function srl_student_lookup_form() {
    ob_start();
    ?>
    <div class="srl-container">
        <form method="post" class="srl-form">
            <h2>Student Result Lookup</h2>
            <p class="srl-subtitle">Enter student details to view results</p>

            <div class="srl-form-group">
                <label>Fullname</label>
                <input type="text" name="srl_fullname" required placeholder="Enter fullname">
            </div>

            <div class="srl-form-group">
                <label>Access Code</label>
                <input type="text" name="srl_access_code" required placeholder="Enter access code">
            </div>

            <div class="srl-form-group">
                <button type="submit" name="srl_submit" class="srl-btn">Check Result</button>
            </div>
        </form>
    </div>
    <?php

    srl_handle_form_submission();
    return ob_get_clean();
}
add_shortcode('student_lookup_form', 'srl_student_lookup_form');






function srl_handle_form_submission() {
    if (!isset($_POST['srl_submit']) && !isset($_POST['srl_fetch_grades'])) return;

    $endpoint = srl_moodle_endpoint();
    $token = srl_moodle_token();

    if (empty($token)) {
        echo '<div class="srl-error">Moodle API token not configured.</div>';
        return;
    }

    if (isset($_POST['srl_submit'])) {
        $fullname = sanitize_text_field($_POST['srl_fullname'] ?? '');
        $access_code = sanitize_text_field($_POST['srl_access_code'] ?? '');

        $student = SRL_Moodle_API::fetch_user_by_idnumber($endpoint, $token, $access_code);
        if (!$student) $student = SRL_Moodle_API::fetch_user_by_idnumber($endpoint, $token, $access_code . '-DEBT');

        if (!$student) {
            echo '<div class="srl-error">No student found. Please check the access code.</div>';
            return;
        }

        if (strcasecmp($student['fullname'], $fullname) !== 0) {
            echo '<div class="srl-error">Details do not match our records.</div>';
            return;
        }

        if (str_ends_with($student['idnumber'], '-DEBT')) {
            echo '<div class="srl-container"><div class="srl-error">You have outstanding fees. Please clear all debts with the school management to view your results.</div></div>';
            return;
        }

        $courses = SRL_Moodle_API::fetch_user_courses($endpoint, $token, $student['id']);
        if ($courses === null) {
            echo '<div class="srl-error">Unable to fetch courses.</div>';
            return;
        }

        if (empty($courses)) {
            echo '<div class="srl-error">No courses found for this student.</div>';
            return;
        }

        if (count($courses) > 1) {
            echo '<div class="srl-container">';
            echo '<form method="post" class="srl-form">';
            echo '<div class="srl-success">Student found: ' . esc_html($student['firstname'] . ' ' . $student['lastname']) . '</div>';
            echo '<input type="hidden" name="srl_student_id" value="' . esc_attr($student['id']) . '">';
            echo '<input type="hidden" name="srl_fullname" value="' . esc_attr($fullname) . '">';
            echo '<input type="hidden" name="srl_access_code" value="' . esc_attr($access_code) . '">';
            echo '<div class="srl-form-group">';
            echo '<label>Select Term/Session:</label>';
            echo '<select name="srl_course_id">';
            foreach ($courses as $course) {
                echo '<option value="' . esc_attr($course['id']) . '">' . esc_html($course['fullname']) . '</option>';
            }
            echo '</select></div>';
            echo '<div class="srl-form-group"><button type="submit" name="srl_fetch_grades" class="srl-btn">View Grades</button></div>';
            echo '</form></div>';
            return;
        }

        srl_display_grades($token, $endpoint, $student['id'], $courses[0]['id']);
        return;
    }

    if (isset($_POST['srl_fetch_grades'])) {
        $student_id = sanitize_text_field($_POST['srl_student_id'] ?? '');
        $course_id = sanitize_text_field($_POST['srl_course_id'] ?? '');
        srl_display_grades($token, $endpoint, $student_id, $course_id);
    }
}
