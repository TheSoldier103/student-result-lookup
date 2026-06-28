<?php
/**
 * Plugin Name: Student Result Lookup
 * Description: Parent-facing form to lookup student courses and grades dynamically from Moodle.
 * Version: 5.9-DEBT-FUNCTIONALITY
 * Author: Petra Christian Academy
 */

if (!defined('ABSPATH')) exit;

// Include PDF Generator class
require_once plugin_dir_path(__FILE__) . 'includes/class-pdf-generator.php';

// Handle PDF download request
add_action('init', 'srl_handle_pdf_download');

/**
 * Handle PDF download request
 */
function srl_handle_pdf_download() {
    if (!isset($_GET['srl_download_pdf']) || !isset($_GET['srl_nonce'])) {
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_GET['srl_nonce'], 'srl_pdf_download')) {
        wp_die('Security check failed');
    }
    
    // Get parameters
    $student_id = isset($_GET['student_id']) ? sanitize_text_field($_GET['student_id']) : '';
    $course_id = isset($_GET['course_id']) ? sanitize_text_field($_GET['course_id']) : '';
    
    if (empty($student_id) || empty($course_id)) {
        wp_die('Missing required parameters.');
    }
    
    // CRITICAL: Suppress all errors/notices to prevent output before PDF
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Clean all output buffers before PDF generation
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fetch all required data
    $moodle_endpoint = 'https://learn.petrachristianacademy.com/webservice/rest/server.php';
    $token = defined('MOODLE_API_TOKEN') ? MOODLE_API_TOKEN : '';
    
    if (empty($token)) {
        wp_die('Moodle API token not configured.');
    }
    
    // Get grades
    $grades_data = srl_fetch_grades($moodle_endpoint, $token, $student_id, $course_id);
    
    // Get student details
    $student_data = srl_fetch_student_details($moodle_endpoint, $token, $grades_data['usergrades'][0]['useridnumber']);
    
    // Get course info
    $course_info = srl_fetch_course_details($moodle_endpoint, $token, $course_id);
    
    // Get forum announcements
    $announcements = srl_fetch_announcements($moodle_endpoint, $token, $course_id);
    
    // Get teacher and principal
    $staff = srl_fetch_staff($moodle_endpoint, $token, $course_id);
    
    // Generate PDF (this will handle its own output buffering)
    $pdf_generator = new SRL_PDF_Generator();
    $pdf_generator->generate_report_card([
        'grades_data' => $grades_data,
        'student_data' => $student_data,
        'course_info' => $course_info,
        'announcements' => $announcements,
        'staff' => $staff
    ]);
}

/**
 * Shortcode: [student_lookup_form]
 */
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

/**
 * Add custom styles
 */
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


/**
 * Handle form submission
 */
function srl_handle_form_submission() {
    if (!isset($_POST['srl_submit']) && !isset($_POST['srl_fetch_grades'])) return;

    $moodle_endpoint = 'https://learn.petrachristianacademy.com/webservice/rest/server.php';
    $token = defined('MOODLE_API_TOKEN') ? MOODLE_API_TOKEN : '';

    if (isset($_POST['srl_submit'])) {
        $fullname    = sanitize_text_field($_POST['srl_fullname'] ?? '');
        $access_code = sanitize_text_field($_POST['srl_access_code'] ?? '');

        // --- Student lookup helper ---
        $lookup_user = function($idnumber) use ($token, $moodle_endpoint) {
            $user_params = [
                'wstoken'            => $token,
                'wsfunction'         => 'core_user_get_users',
                'moodlewsrestformat' => 'json',
                'criteria[0][key]'   => 'idnumber',
                'criteria[0][value]' => $idnumber,
            ];
            $response = wp_remote_get($moodle_endpoint . '?' . http_build_query($user_params));
            if (is_wp_error($response)) return null;
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return $data['users'][0] ?? null;
        };

        // First try the plain access code
        $student = $lookup_user($access_code);

        // If not found, try with -DEBT suffix
        if (!$student) {
            $student = $lookup_user($access_code . '-DEBT');
        }

        if (!$student) {
            echo '<div class="srl-error">No student found. Please check the access code.</div>';
            return;
        }

        // Verify fullname
        if (strcasecmp($student['fullname'], $fullname) !== 0) {
            echo '<div class="srl-error">Details do not match our records.</div>';
            return;
        }

        // í ½íº« Check debt against what Moodle actually has on record, not what the student typed
        if (str_ends_with($student['idnumber'], '-DEBT')) {
            echo '<div class="srl-container">';
            echo '<div class="srl-error">You have outstanding fees. Please clear all debts with the school management to view your results.</div>';
            echo '</div>';
            return;
        }

        // Fetch courses
        $course_params = [
            'wstoken'            => $token,
            'wsfunction'         => 'core_enrol_get_users_courses',
            'moodlewsrestformat' => 'json',
            'userid'             => $student['id'],
        ];
        $course_response = wp_remote_get($moodle_endpoint . '?' . http_build_query($course_params));

        if (is_wp_error($course_response)) {
            echo '<div class="srl-error">Unable to fetch courses.</div>';
            return;
        }

        $courses = json_decode(wp_remote_retrieve_body($course_response), true);

        if (empty($courses)) {
            echo '<div class="srl-error">No courses found for this student.</div>';
            return;
        }

        // Multiple courses â†’ show dropdown
        if (count($courses) > 1) {
            echo '<div class="srl-container">';
            echo '<form method="post" class="srl-form">';
            echo '<div class="srl-success">Student found: ' . esc_html($student['firstname'] . ' ' . $student['lastname']) . '</div>';
            echo '<input type="hidden" name="srl_student_id"  value="' . esc_attr($student['id']) . '">';
            echo '<input type="hidden" name="srl_fullname"    value="' . esc_attr($fullname) . '">';
            echo '<input type="hidden" name="srl_access_code" value="' . esc_attr($access_code) . '">';
            echo '<div class="srl-form-group">';
            echo '<label>Select Term/Session:</label>';
            echo '<select name="srl_course_id">';
            foreach ($courses as $c) {
                echo '<option value="' . esc_attr($c['id']) . '">' . esc_html($c['fullname']) . '</option>';
            }
            echo '</select></div>';
            echo '<div class="srl-form-group"><button type="submit" name="srl_fetch_grades" class="srl-btn">View Grades</button></div>';
            echo '</form></div>';
            return;
        } else {
            // Only one course â†’ auto-fetch grades
            srl_display_grades($token, $moodle_endpoint, $student['id'], $courses[0]['id']);
            return;
        }
    }

    // Fetch grades if requested (from dropdown selection)
    if (isset($_POST['srl_fetch_grades'])) {
        $student_id = sanitize_text_field($_POST['srl_student_id'] ?? '');
        $course_id  = sanitize_text_field($_POST['srl_course_id'] ?? '');

        srl_display_grades($token, $moodle_endpoint, $student_id, $course_id);
    }
}



/**
 * Display grades for a student in a course
 */
function srl_display_grades($token, $moodle_endpoint, $student_id, $course_id) {
    // Fetch grades
    $grades_data = srl_fetch_grades($moodle_endpoint, $token, $student_id, $course_id);
    
    if (!$grades_data) {
        echo '<div class="srl-error">Unable to fetch grades.</div>';
        return;
    }

    $usergrade = $grades_data['usergrades'][0];
    
    // Get student details (sex)
    $student_data = srl_fetch_student_details($moodle_endpoint, $token, $usergrade['useridnumber']);
    
    // Get course details
    $course_info = srl_fetch_course_details($moodle_endpoint, $token, $course_id);

    // í ½íº« Stop if results not ready
    if (empty($course_info['course_complete'])) {
        echo '<div class="srl-container">';
        echo '<div class="srl-error">Exam results are not ready yet. Please check back later.</div>';
        echo '</div>';
        return;
    }
    
    // Get announcements
    $announcements = srl_fetch_announcements($moodle_endpoint, $token, $course_id);
    
    // Organize grade items
    $organized = srl_organize_grade_items($usergrade['gradeitems']);

    // Start rendering
    echo '<div class="srl-container">';
    
    // PDF Download button
    $pdf_url = add_query_arg([
        'srl_download_pdf' => '1',
        'student_id' => (int)$usergrade['userid'],
        'course_id' => (int)$usergrade['courseid'],
        'srl_nonce' => wp_create_nonce('srl_pdf_download')
    ], home_url());
    
    echo '<a href="' . esc_url($pdf_url) . '" class="srl-btn" style="display:inline-block; text-decoration:none; margin-bottom:15px;">
        Download Report Card (PDF)
    </a>';
    
    echo '<button onclick="window.print()" class="srl-print-btn">Print Report Card</button>';
    
    echo '<div class="srl-report-card">';
    
    // Header
    echo '<div class="srl-header">';
    echo '<h2>Petra Christian Academy</h2>';
    echo '<p style="font-style:italic; color:#666; margin:5px 0;">Righteousness and Excellence</p>';
    echo '<div class="student-name">' . esc_html($usergrade['userfullname']) . '</div>';
    echo '</div>';
    
    // Student info
    echo '<div class="srl-info-grid">';
    echo '<div class="srl-info-item"><strong>Sex:</strong> ' . esc_html($student_data['sex'] ?? 'N/A') . '</div>';
    echo '<div class="srl-info-item"><strong>Class:</strong> ' . esc_html($course_info['class']) . '</div>';
    echo '<div class="srl-info-item"><strong>Term:</strong> ' . esc_html($course_info['term']) . '</div>';
    echo '<div class="srl-info-item"><strong>Session:</strong> ' . esc_html($course_info['session']) . '</div>';
    echo '</div>';

    // Performance Summary
    if ($organized['course_total']) {
        $total = $organized['course_total'];
        echo '<div class="srl-section-title">Performance Summary</div>';
        echo '<div class="srl-performance-summary">';
        
        $position = srl_format_position($total['rank'] ?? null);
        echo '<div class="srl-stat-card">';
        echo '<div class="srl-stat-label">Position</div>';
        echo '<div class="srl-stat-value">' . esc_html($position) . ' out of ' . esc_html($total['numusers'] ?? 'N/A') . '</div>';
        echo '</div>';
        
        echo '<div class="srl-stat-card">';
        echo '<div class="srl-stat-label">Total Obtained</div>';
        echo '<div class="srl-stat-value">' . esc_html($total['gradeformatted']) . '</div>';
        echo '</div>';
        
        echo '<div class="srl-stat-card">';
        echo '<div class="srl-stat-label">Total Obtainable</div>';
        echo '<div class="srl-stat-value">' . esc_html($total['grademax']) . '</div>';
        echo '</div>';
        
        echo '<div class="srl-stat-card">';
        echo '<div class="srl-stat-label">Percentage</div>';
        echo '<div class="srl-stat-value">' . esc_html($total['percentageformatted']) . '</div>';
        echo '</div>';
        
        echo '</div>';
    }

    // Subject Performance
    if (!empty($organized['subjects'])) {
        echo '<div class="srl-section-title">Subject Performance</div>';
        
        foreach ($organized['subjects'] as $subject_name => $subject_data) {
            $category = $subject_data['category'];
            $components = $subject_data['components'];
            
            echo '<div class="srl-subject-card">';
            echo '<div class="srl-subject-header">';
            echo '<span class="srl-subject-name">' . esc_html($subject_name) . '</span>';
            echo '<span class="srl-subject-total">Total: ' . esc_html($category['gradeformatted']) . '/' . esc_html($category['grademax']) . ' | Grade: ' . esc_html($category['lettergradeformatted']) . ' | Position: ' . esc_html(srl_format_position($category['rank'] ?? null)) . ' out of ' . esc_html($category['numusers'] ?? 'N/A') . '</span>';
            echo '</div>';
            
            if (!empty($components)) {
                echo '<div class="srl-subject-breakdown">';
                foreach ($components as $comp) {
                    $comp_name = str_replace($subject_name . ' - ', '', $comp['itemname']);
                    echo '<div class="srl-breakdown-item"><strong>' . esc_html($comp_name) . ':</strong> ' . esc_html($comp['gradeformatted']) . '/' . esc_html($comp['grademax']) . '</div>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        }
    }

    // Attendance
    if ($organized['attendance']['opened'] || $organized['attendance']['present']) {
        echo '<div class="srl-section-title">Attendance Summary</div>';
        echo '<div class="srl-attendance-grid">';
        
        echo '<div class="srl-attendance-item">';
        echo '<strong>' . esc_html($announcements['days_opened'] ?? 'N/A') . '</strong>';
        echo '<span>Days School Opened</span>';
        echo '</div>';
        
        echo '<div class="srl-attendance-item">';
        echo '<strong>' . esc_html($organized['attendance']['present'] ?? 'N/A') . '</strong>';
        echo '<span>Days Present</span>';
        echo '</div>';
        
        echo '</div>';
    }

    // Remarks
    if ($organized['remarks']['teacher'] || $organized['remarks']['principal']) {
        echo '<div class="srl-section-title">Remarks</div>';
        echo '<div class="srl-remarks-grid">';
        
        if ($organized['remarks']['teacher']) {
            echo '<div class="srl-remark-box">';
            echo '<h4>Class Teacher\'s Remark</h4>';
            echo '<p>' . wp_kses_post($organized['remarks']['teacher']) . '</p>';
            echo '</div>';
        }
        
        if ($organized['remarks']['principal']) {
            echo '<div class="srl-remark-box">';
            echo '<h4>Principal\'s/Vice-Principal\'s Remarks</h4>';
            echo '<p>' . wp_kses_post($organized['remarks']['principal']) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    // Announcements
    if (!empty($announcements)) {
        echo '<div class="srl-section-title">Announcements</div>';
        
        if (!empty($announcements['next_term'])) {
            echo '<p><strong>Next Term Begins:</strong> ' . esc_html($announcements['next_term']) . '</p>';
        }
        if (!empty($announcements['fees'])) {
            echo '<p><strong>Fees for Next Term:</strong> ' . esc_html($announcements['fees']) . '</p>';
        }
        if (!empty($announcements['general'])) {
            echo '<p><strong>Announcement:</strong> ' . wp_kses_post($announcements['general']) . '</p>';
        }
    }

    echo '</div>'; // .srl-report-card
    echo '</div>'; // .srl-container
}

/**
 * Fetch grades from Moodle
 */
function srl_fetch_grades($endpoint, $token, $student_id, $course_id) {
    $params = [
        'wstoken' => $token,
        'wsfunction' => 'gradereport_user_get_grade_items',
        'moodlewsrestformat' => 'json',
        'userid' => $student_id,
        'courseid' => $course_id,
    ];

    $response = wp_remote_get($endpoint . '?' . http_build_query($params));
    
    if (is_wp_error($response)) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($data['usergrades'])) {
        return null;
    }
    
    return $data;
}

/**
 * Fetch student details (including sex)
 */
function srl_fetch_student_details($endpoint, $token, $idnumber) {
    $params = [
        'wstoken' => $token,
        'wsfunction' => 'core_user_get_users',
        'moodlewsrestformat' => 'json',
        'criteria[0][key]' => 'idnumber',
        'criteria[0][value]' => $idnumber,
    ];

    $response = wp_remote_get($endpoint . '?' . http_build_query($params));
    
    if (is_wp_error($response)) {
        return ['sex' => 'N/A'];
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($data['users'][0])) {
        return ['sex' => 'N/A'];
    }
    
    $user = $data['users'][0];
    $sex = 'N/A';
    
    if (!empty($user['customfields'])) {
        foreach ($user['customfields'] as $field) {
            if ($field['shortname'] === 'Sex') {
                $sex = $field['value'];
                break;
            }
        }
    }
    
    return ['sex' => $sex];
}

/**
 * Fetch course details
 */
function srl_fetch_course_details($endpoint, $token, $course_id) {
    $params = [
        'wstoken' => $token,
        'wsfunction' => 'core_course_get_courses',
        'moodlewsrestformat' => 'json',
        'options[ids][0]' => $course_id,
    ];

    $response = wp_remote_get($endpoint . '?' . http_build_query($params));
    
    if (is_wp_error($response)) {
        return ['class' => 'N/A', 'term' => 'N/A', 'session' => 'N/A', 'course_complete' => 0];
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($data[0])) {
        return ['class' => 'N/A', 'term' => 'N/A', 'session' => 'N/A', 'course_complete' => 0];
    }

    $course = $data[0];
    $parsed = srl_parse_course_info($course['shortname'] ?? '');

    $course_complete = 0;

    if (!empty($course['customfields'])) {
        foreach ($course['customfields'] as $field) {
            if ($field['shortname'] === 'course_complete') {
                $course_complete = (int)($field['valueraw'] ?? 0);
                break;
            }
        }
    }

    $parsed['course_complete'] = $course_complete;

    return $parsed;
}

/**
 * Parse course info (class, term, session)
 */
function srl_parse_course_info($course_shortname) {
    // Expected format: SS1-2nd Term-2025/2026
    $parts = explode('-', $course_shortname);
    
    return [
        'class' => trim($parts[0] ?? 'N/A'),
        'term' => trim($parts[1] ?? 'N/A'),
        'session' => trim($parts[2] ?? 'N/A'),
    ];
}

/**
 * Fetch announcements from forum
 */
function srl_fetch_announcements($endpoint, $token, $course_id) {
    // Get forums
    $forum_params = [
        'wstoken' => $token,
        'wsfunction' => 'mod_forum_get_forums_by_courses',
        'moodlewsrestformat' => 'json',
        'courseids[0]' => $course_id,
    ];

    $forum_response = wp_remote_get($endpoint . '?' . http_build_query($forum_params));
    
    if (is_wp_error($forum_response)) {
        return [];
    }

    $forums = json_decode(wp_remote_retrieve_body($forum_response), true);
    
    // Find the Announcements forum
    $forum_id = null;
    foreach ($forums as $forum) {
        if ($forum['type'] === 'news' && $forum['name'] === 'Announcements') {
            $forum_id = $forum['id'];
            break;
        }
    }
    
    if (!$forum_id) {
        return [];
    }
    
    // Get discussions
    $discussion_params = [
        'wstoken' => $token,
        'wsfunction' => 'mod_forum_get_forum_discussions',
        'moodlewsrestformat' => 'json',
        'forumid' => $forum_id,
    ];

    $discussion_response = wp_remote_get($endpoint . '?' . http_build_query($discussion_params));
    
    if (is_wp_error($discussion_response)) {
        return [];
    }

    $data = json_decode(wp_remote_retrieve_body($discussion_response), true);
    
    if (empty($data['discussions'])) {
        return [];
    }
    
    $announcements = [
        'days_opened' => null,
        'next_term' => null,
        'fees' => null,
        'general' => null,
    ];
    
    foreach ($data['discussions'] as $discussion) {
        $subject = strtolower($discussion['subject']);
        $message = strip_tags($discussion['message']);
        
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

/**
 * Fetch staff (teacher and principal)
 */
function srl_fetch_staff($endpoint, $token, $course_id) {
    $params = [
        'wstoken' => $token,
        'wsfunction' => 'core_enrol_get_enrolled_users',
        'moodlewsrestformat' => 'json',
        'courseid' => $course_id,
    ];

    $response = wp_remote_get($endpoint . '?' . http_build_query($params));
    
    if (is_wp_error($response)) {
        return ['teacher' => 'N/A', 'principal' => 'N/A'];
    }

    $users = json_decode(wp_remote_retrieve_body($response), true);
    
    $staff = ['teacher' => 'N/A', 'principal' => 'N/A'];
    
    foreach ($users as $user) {
        if (!empty($user['roles'])) {
            foreach ($user['roles'] as $role) {
                if ($role['shortname'] === 'editingteacher') {
                    $staff['teacher'] = $user['fullname'];
                } elseif ($role['shortname'] === 'principal') {
                    $staff['principal'] = $user['fullname'];
                }
            }
        }
    }
    
    return $staff;
}

/**
 * Organize grade items by subject categories
 */
function srl_organize_grade_items($gradeitems) {
    $subjects = [];
    $course_total = null;
    $attendance = ['opened' => null, 'present' => null];
    $remarks = ['teacher' => '', 'principal' => ''];
    
    // First pass: identify categories and their components
    $categories = [];
    $components_by_category = [];
    
    foreach ($gradeitems as $item) {
        if ($item['itemtype'] === 'course') {
            $course_total = $item;
        } elseif ($item['itemtype'] === 'category') {
            $categories[$item['iteminstance']] = $item;
        } elseif ($item['itemtype'] === 'mod') {
            if (isset($item['categoryid'])) {
                $components_by_category[$item['categoryid']][] = $item;
            }
        } elseif ($item['itemtype'] === 'manual') {
            if (isset($item['itemname']) && stripos($item['itemname'], 'Days Present') !== false) {
                $attendance['present'] = $item['gradeformatted'];
            } elseif (isset($item['itemname']) && stripos($item['itemname'], 'Teacher') !== false && stripos($item['itemname'], 'Remark') !== false) {
                $remarks['teacher'] = $item['feedback'];
            } elseif (isset($item['itemname']) && stripos($item['itemname'], 'Principal') !== false && stripos($item['itemname'], 'Remark') !== false) {
                $remarks['principal'] = $item['feedback'];
            }
        }
    }
    
    // Second pass: build subject structure
    foreach ($categories as $cat_id => $category) {
        $subject_name = $category['itemname'];
        $subjects[$subject_name] = [
            'category' => $category,
            'components' => $components_by_category[$cat_id] ?? []
        ];
    }
    
    return [
        'subjects' => $subjects,
        'course_total' => $course_total,
        'attendance' => $attendance,
        'remarks' => $remarks
    ];
}

/**
 * Format position with ordinal suffix (1st, 2nd, 3rd, etc.)
 */
function srl_format_position($number) {
    if (!is_numeric($number) || $number <= 0) return 'N/A';
    
    $number = (int)$number;
    $suffix = 'th';
    
    if (!in_array(($number % 100), [11, 12, 13])) {
        switch ($number % 10) {
            case 1: $suffix = 'st'; break;
            case 2: $suffix = 'nd'; break;
            case 3: $suffix = 'rd'; break;
        }
    }
    
    return $number . $suffix;
}
