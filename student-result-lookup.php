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

function srl_moodle_endpoint() {
    return 'https://learn.petrachristianacademy.com/webservice/rest/server.php';
}

function srl_moodle_token() {
    return defined('MOODLE_API_TOKEN') ? MOODLE_API_TOKEN : '';
}

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
    srl_add_custom_styles();
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

function srl_add_custom_styles() {
    ?>
    <style>
        .srl-container {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            max-width: 900px;
            margin: 20px auto;
        }
        .srl-form {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .srl-form h2 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 24px;
        }
        .srl-subtitle {
            color: #7f8c8d;
            margin: 0 0 25px 0;
            font-size: 14px;
        }
        .srl-form-group {
            margin-bottom: 20px;
        }
        .srl-form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 500;
            font-size: 14px;
        }
        .srl-form-group input, .srl-form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .srl-form-group input:focus, .srl-form-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        .srl-btn {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        .srl-btn:hover {
            background: #2980b9;
        }
        .srl-error {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 5px;
            border-left: 4px solid #c33;
            margin: 15px 0;
        }
        .srl-success {
            background: #efe;
            color: #3c763d;
            padding: 12px 15px;
            border-radius: 5px;
            border-left: 4px solid #3c763d;
            margin: 15px 0;
        }
        .srl-report-card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .srl-header {
            text-align: center;
            border-bottom: 3px solid #3498db;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .srl-header h2 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 28px;
        }
        .srl-header .student-name {
            color: #3498db;
            font-size: 20px;
            font-weight: 600;
            margin: 10px 0 5px 0;
        }
        .srl-header .course-name {
            color: #7f8c8d;
            font-size: 16px;
        }
        .srl-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .srl-info-item {
            font-size: 14px;
        }
        .srl-info-item strong {
            color: #2c3e50;
        }
        .srl-section-title {
            background: #ecf0f1;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: 600;
            color: #2c3e50;
            margin: 25px 0 15px 0;
            font-size: 16px;
        }
        .srl-subject-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .srl-subject-header {
            background: #34495e;
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .srl-subject-name {
            font-weight: 600;
            font-size: 15px;
        }
        .srl-subject-total {
            font-size: 14px;
        }
        .srl-subject-breakdown {
            padding: 12px 15px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            background: #f8f9fa;
        }
        .srl-breakdown-item {
            font-size: 13px;
        }
        .srl-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: #fff;
        }
        .srl-table th {
            background: #34495e;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .srl-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #ecf0f1;
            color: #2c3e50;
        }
        .srl-table tr:last-child td {
            border-bottom: none;
        }
        .srl-table tr:hover {
            background: #f8f9fa;
        }
        .srl-performance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .srl-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .srl-stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .srl-stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .srl-stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .srl-stat-card:nth-child(5) {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .srl-stat-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .srl-stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-top: 8px;
        }
        .srl-remarks-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .srl-remark-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        .srl-remark-box h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 14px;
            font-weight: 600;
        }
        .srl-remark-box p {
            margin: 0;
            color: #555;
            line-height: 1.6;
        }
        .srl-attendance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .srl-attendance-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .srl-attendance-item strong {
            display: block;
            color: #3498db;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .srl-attendance-item span {
            color: #7f8c8d;
            font-size: 13px;
        }
        .srl-print-btn {
            background: #27ae60;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
            margin: 0 0 20px 10px;
            display: inline-block;
            text-decoration: none;
        }
        .srl-print-btn:hover {
            background: #229954;
        }
        @media print {
            .srl-form, .srl-print-btn, .srl-btn { display: none; }
            .srl-report-card { box-shadow: none; }
            body { background: white; }
        }
    </style>
    <?php
}




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
