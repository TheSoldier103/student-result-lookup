<?php
/**
 * Web renderer coordinator.
 * Delegates 1st/2nd term rendering and 3rd term rendering to separate files.
 */
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'renderers/class-standard-term-web-renderer.php';
require_once plugin_dir_path(__FILE__) . 'renderers/class-third-term-web-renderer.php';

class SRL_Web_Renderer {


public static function display_grades($token, $moodle_endpoint, $student_id, $course_id) {
    $grades_data = srl_fetch_grades($moodle_endpoint, $token, $student_id, $course_id);
    if (!$grades_data) {
        echo '<div class="srl-error">Unable to fetch grades.</div>';
        return;
    }
 
    $usergrade    = $grades_data['usergrades'][0];
    $student_data = srl_fetch_student_details($moodle_endpoint, $token, $usergrade['useridnumber']);
    $course_info  = srl_fetch_course_details($moodle_endpoint, $token, $course_id);
 
    if (empty($course_info['course_complete'])) {
        echo '<div class="srl-container"><div class="srl-error">Exam results are not ready yet. Please check back later.</div></div>';
        return;
    }
 
    $announcements = srl_fetch_announcements($moodle_endpoint, $token, $course_id);
    $is_third_term = ($course_info['term'] === '3rd Term');
    $is_exit_class = srl_is_exit_class($course_info['class']);
    $organized     = srl_organize_grade_items($usergrade['gradeitems']);
 
    echo '<div class="srl-container">';
 
    $pdf_url = add_query_arg([
        'srl_download_pdf' => '1',
        'student_id'       => (int)$usergrade['userid'],
        'course_id'        => (int)$usergrade['courseid'],
        'srl_nonce'        => wp_create_nonce('srl_pdf_download'),
    ], home_url());
 
    echo '<a href="' . esc_url($pdf_url) . '" class="srl-btn" style="display:inline-block;text-decoration:none;margin-bottom:15px;">Download Report Card (PDF)</a>';
    echo '<button onclick="srlPrintReport(' . esc_attr(wp_json_encode($usergrade['userfullname'])) . ')" class="srl-print-btn">Print Report Card</button>';
    echo '<div class="srl-report-card">';
 
    // Header
    echo '<div class="srl-header">';
    echo '<h2>Petra Christian Academy</h2>';
    echo '<p style="font-style:italic;color:#666;margin:5px 0;">Righteousness and Excellence</p>';
    echo '<div class="student-name">' . esc_html($usergrade['userfullname']) . '</div>';
    echo '</div>';
 
    // Student info
    echo '<div class="srl-info-grid">';
    echo '<div class="srl-info-item"><strong>Sex:</strong> '     . esc_html($student_data['sex'] ?? 'N/A') . '</div>';
    echo '<div class="srl-info-item"><strong>Class:</strong> '   . esc_html($course_info['class'])          . '</div>';
    echo '<div class="srl-info-item"><strong>Term:</strong> '    . esc_html($course_info['term'])           . '</div>';
    echo '<div class="srl-info-item"><strong>Session:</strong> ' . esc_html($course_info['session'])        . '</div>';
    echo '</div>';
 
    // ---- Performance Summary ----
    if ($is_third_term) {
        SRL_Third_Term_Web_Renderer::render_performance_summary(
            $organized,
            $course_id,
            $student_id,
            $is_exit_class
        );
    } else {
        SRL_Standard_Term_Web_Renderer::render_performance_summary($organized);
    }

    // ---- Subject Performance ----
    if (!empty($organized['subjects'])) {
        echo '<div class="srl-section-title">Subject Performance</div>';
        if ($is_third_term) {
            SRL_Third_Term_Web_Renderer::render_subject_table(
                $organized['subjects'],
                $is_exit_class
            );
        } else {
            SRL_Standard_Term_Web_Renderer::render_subject_table($organized['subjects']);
        }
    }

    // ---- Attendance ----
    if ($organized['attendance']['opened'] || $organized['attendance']['present']) {
        echo '<div class="srl-section-title">Attendance Summary</div>';
        echo '<div class="srl-attendance-grid">';
        echo '<div class="srl-attendance-item"><strong>' . esc_html($announcements['days_opened'] ?? 'N/A') . '</strong><span>Days School Opened</span></div>';
        echo '<div class="srl-attendance-item"><strong>' . esc_html($organized['attendance']['present'] ?? 'N/A') . '</strong><span>Days Present</span></div>';
        echo '</div>';
    }
 
    // ---- Remarks ----
    if ($organized['remarks']['teacher'] || $organized['remarks']['principal']) {
        echo '<div class="srl-section-title">Remarks</div>';
        echo '<div class="srl-remarks-grid">';
        if ($organized['remarks']['teacher'])   echo '<div class="srl-remark-box"><h4>Class Teacher\'s Remark</h4><p>' . wp_kses_post($organized['remarks']['teacher']) . '</p></div>';
        if ($organized['remarks']['principal']) echo '<div class="srl-remark-box"><h4>Principal\'s/Vice-Principal\'s Remarks</h4><p>' . wp_kses_post($organized['remarks']['principal']) . '</p></div>';
        echo '</div>';
    }
 
    // ---- Announcements ----
    if (!empty($announcements)) {
        echo '<div class="srl-section-title">Announcements</div>';
        if (!empty($announcements['next_term'])) echo '<p><strong>Next Term Begins:</strong> '   . esc_html($announcements['next_term']) . '</p>';
        if (!empty($announcements['fees']))      echo '<p><strong>Fees for Next Term:</strong> ' . esc_html($announcements['fees'])      . '</p>';
        if (!empty($announcements['general']))   echo '<p><strong>Announcement:</strong> '       . wp_kses_post($announcements['general']) . '</p>';
    }
 
    echo '</div>'; // .srl-report-card
    echo '<script>
    function srlPrintReport(studentName) {
        var originalTitle = document.title;
        var cleanName = String(studentName || "Student").replace(/[^A-Za-z0-9 _-]/g, "").trim().replace(/\s+/g, "_");
        document.title = cleanName + "_Report_Card";
        window.addEventListener("afterprint", function restoreSrlTitle() {
            document.title = originalTitle;
            window.removeEventListener("afterprint", restoreSrlTitle);
        }, { once: true });
        window.print();
        setTimeout(function() { document.title = originalTitle; }, 1500);
    }
    </script>';
    echo '</div>'; // .srl-container
}

    // Backward-compatible wrappers used by existing global helper functions.
    public static function render_exit_class_subject_table($subjects) {
        return SRL_Third_Term_Web_Renderer::render_exit_class_subject_table($subjects);
    }

    public static function render_standard_third_term_subject_table($subjects) {
        return SRL_Third_Term_Web_Renderer::render_standard_third_term_subject_table($subjects);
    }

    public static function render_third_term_subject_table($subjects) {
        return SRL_Third_Term_Web_Renderer::render_third_term_subject_table($subjects);
    }

    public static function render_standard_subject_table($subjects) {
        return SRL_Standard_Term_Web_Renderer::render_subject_table($subjects);
    }
}
