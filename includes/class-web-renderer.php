<?php
/**
 * Web HTML renderer for Student Result Lookup.
 */
if (!defined('ABSPATH')) exit;

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
    echo '<button onclick="window.print()" class="srl-print-btn">Print Report Card</button>';
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
    if ($organized['course_total']) {
        $total    = $organized['course_total'];
        $position = srl_format_position($total['rank'] ?? null) . ' out of ' . ($total['numusers'] ?? 'N/A');
 
        echo '<div class="srl-section-title">Performance Summary</div>';
        echo '<div class="srl-performance-summary">';
 
        $label_suffix = $is_third_term ? ' (3rd)' : '';
 
        foreach ([
            ['Position' . $label_suffix,         $position],
            ['Total Obtained' . $label_suffix,    $total['gradeformatted']],
            ['Total Obtainable' . $label_suffix,  $total['grademax']],
            ['Percentage' . $label_suffix,        $total['percentageformatted']],
        ] as [$label, $value]) {
            echo '<div class="srl-stat-card">';
            echo '<div class="srl-stat-label">' . $label . '</div>';
            echo '<div class="srl-stat-value">' . esc_html($value) . '</div>';
            echo '</div>';
        }
 
        if ($is_third_term) {
            $cum = srl_calc_overall_cumulative_summary($organized['subjects']);
            echo '<div class="srl-stat-card"><div class="srl-stat-label">Cumulative Total</div><div class="srl-stat-value">' . esc_html($cum['total']) . '</div></div>';
            echo '<div class="srl-stat-card"><div class="srl-stat-label">Cumulative Average</div><div class="srl-stat-value">' . esc_html($cum['avg']) . '</div></div>';
        }
 
        echo '</div>';
    }
 
    // ---- Subject Performance ----
    if (!empty($organized['subjects'])) {
        echo '<div class="srl-section-title">Subject Performance</div>';
        if ($is_third_term && $is_exit_class) {
            srl_render_exit_class_subject_table($organized['subjects']);
        } elseif ($is_third_term) {
            srl_render_standard_third_term_subject_table($organized['subjects']);
        } else {
            srl_render_standard_subject_table($organized['subjects']);
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
    echo '</div>'; // .srl-container
}


/** Web table — exit class 3rd term (JSS3/SS3) */
public static function render_exit_class_subject_table($subjects) {
    echo '<div style="overflow-x:auto;">';
    echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
 
    echo '<thead>';
    echo '<tr style="background:#34495e;color:#fff;text-align:center;">
        <th rowspan="2" style="padding:6px;text-align:left;border:1px solid #ccc;">Subject</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">1st Term (100)</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">2nd Term (100)</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">3rd Term Total (100)</th>
        <th colspan="4" style="padding:6px;border:1px solid #ccc;background:#083c78;">CUMULATIVE</th>
    </tr>
    <tr style="background:#083c78;color:#fff;text-align:center;">
        <th style="padding:6px;border:1px solid #ccc;">Total</th>
        <th style="padding:6px;border:1px solid #ccc;">Avg</th>
        <th style="padding:6px;border:1px solid #ccc;">Grade</th>
        <th style="padding:6px;border:1px solid #ccc;">Position</th>
    </tr>';
    echo '</thead><tbody>';
 
    $row = 0;
    foreach ($subjects as $subject_name => $subject_data) {
        $row++;
        $bg    = ($row % 2 === 0) ? '#f9f9f9' : '#ffffff';
        $terms = srl_extract_term_totals($subject_data['direct_mods']);
 
        $third_total = $terms['term3']['formatted'];
        $third_raw   = $terms['term3']['graderaw'];
 
        $parent    = $subject_data['category'];
        $cum_total = $parent['gradeformatted'];
        $cum_pos   = (($parent['rank'] ?? 0) > 0) ? srl_format_position($parent['rank']) : 'N/A';
 
        $cum_data  = srl_calc_cum_avg($terms, $third_raw);
        $cum_avg   = $cum_data['avg'] !== null ? number_format($cum_data['avg'], 2) : '-';
        $cum_grade = $cum_data['avg'] !== null ? srl_derive_grade($cum_data['avg']) : '-';
 
        echo '<tr style="background:' . $bg . ';text-align:center;">
            <td style="padding:5px;text-align:left;border:1px solid #ddd;">'  . esc_html($subject_name)             . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($terms['term1']['formatted']) . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($terms['term2']['formatted']) . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($third_total)              . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum_total)                . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum_avg)                  . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum_grade)                . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($cum_pos)                  . '</td>
        </tr>';
    }
 
    echo '</tbody></table></div>';
}


/** Web table — standard 3rd term (JSS1/JSS2/SS1/SS2) */
public static function render_standard_third_term_subject_table($subjects) {
    echo '<div style="overflow-x:auto;">';
    echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
 
    // Two-row header: span "CUMULATIVE" over last 4 columns
    echo '<thead>';
    echo '<tr style="background:#34495e;color:#fff;text-align:center;">
        <th rowspan="2" style="padding:6px;text-align:left;border:1px solid #ccc;">Subject</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">1st Term (100)</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">2nd Term (100)</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">CA (20)</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">1st Exam (30)</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">2nd Exam (50)</th>
        <th rowspan="2" style="padding:6px;border:1px solid #ccc;">3rd Term Total (100)</th>
        <th colspan="4" style="padding:6px;border:1px solid #ccc;background:#083c78;">CUMULATIVE</th>
    </tr>
    <tr style="background:#083c78;color:#fff;text-align:center;">
        <th style="padding:6px;border:1px solid #ccc;">Total</th>
        <th style="padding:6px;border:1px solid #ccc;">Avg</th>
        <th style="padding:6px;border:1px solid #ccc;">Grade</th>
        <th style="padding:6px;border:1px solid #ccc;">Position</th>
    </tr>';
    echo '</thead><tbody>';
 
    $row = 0;
    foreach ($subjects as $subject_name => $subject_data) {
        $row++;
        $bg         = ($row % 2 === 0) ? '#f9f9f9' : '#ffffff';
        $terms      = srl_extract_term_totals($subject_data['direct_mods']);
        $components = srl_extract_third_term_components($subject_data['third_subcat_mods']);
        $third_subcat = $subject_data['third_subcat'];
 
        $third_total = $third_subcat ? $third_subcat['gradeformatted'] : '-';
        $third_raw   = $third_subcat ? ($third_subcat['graderaw'] ?? null) : null;
        $cum_pos     = ($third_subcat && ($third_subcat['rank'] ?? 0) > 0)
                            ? srl_format_position($subject_data['category']['rank'])
                            : 'N/A';
 
        // Cum position from parent category
        $parent  = $subject_data['category'];
        $cum_pos = (($parent['rank'] ?? 0) > 0) ? srl_format_position($parent['rank']) : 'N/A';
 
        $cum_data  = srl_calc_cum_avg($terms, $third_raw);
        $cum_avg   = $cum_data['avg'] !== null ? number_format($cum_data['avg'], 2) : '-';
        $cum_grade = $cum_data['avg'] !== null ? srl_derive_grade($cum_data['avg']) : '-';
        $cum_total = $parent['gradeformatted'];
 
        echo '<tr style="background:' . $bg . ';text-align:center;">
            <td style="padding:5px;text-align:left;border:1px solid #ddd;">'  . esc_html($subject_name)             . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($terms['term1']['formatted']) . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($terms['term2']['formatted']) . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($components['ca'])          . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($components['exam1'])       . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($components['exam2'])       . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($third_total)              . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum_total)                . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum_avg)                  . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum_grade)                . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                  . esc_html($cum_pos)                  . '</td>
        </tr>';
    }
 
    echo '</tbody></table></div>';
}


// ============================================================
// NEW HELPER — 3rd term subject table for the web view
// ============================================================

public static function render_third_term_subject_table($subjects) {
    echo '<div style="overflow-x:auto;">';
    echo '<table class="srl-grades-table" style="width:100%;border-collapse:collapse;font-size:13px;">';
    echo '<thead><tr style="background:#34495e;color:#fff;text-align:center;">
        <th style="padding:6px;text-align:left;border:1px solid #ccc;">Subject</th>
        <th style="padding:6px;border:1px solid #ccc;">CA (20)</th>
        <th style="padding:6px;border:1px solid #ccc;">1st Exam (30)</th>
        <th style="padding:6px;border:1px solid #ccc;">2nd Exam (50)</th>
        <th style="padding:6px;border:1px solid #ccc;">3rd Term Total (100)</th>
        <th style="padding:6px;border:1px solid #ccc;">Grade (3rd)</th>
        <th style="padding:6px;border:1px solid #ccc;">Position (3rd)</th>
        <th style="padding:6px;border:1px solid #ccc;">1st Term (100)</th>
        <th style="padding:6px;border:1px solid #ccc;">2nd Term (100)</th>
        <th style="padding:6px;border:1px solid #ccc;">Cum. Total</th>
        <th style="padding:6px;border:1px solid #ccc;">Cum. Avg</th>
    </tr></thead><tbody>';
 
    $row = 0;
    foreach ($subjects as $subject_name => $subject_data) {
        $row++;
        $bg         = ($row % 2 === 0) ? '#f9f9f9' : '#ffffff';
        $category   = $subject_data['category'];
        $components = $subject_data['components'];
 
        $ca = $exam1 = $exam2 = $term1 = $term2 = '-';
 
        foreach ($components as $comp) {
            $type = srl_get_component_type_third($comp['itemname'], $subject_name);
            switch ($type) {
                case 'CA':       $ca    = $comp['gradeformatted']; break;
                case '1ST_EXAM': $exam1 = $comp['gradeformatted']; break;
                case '2ND_EXAM': $exam2 = $comp['gradeformatted']; break;
                case '1ST_TERM': $term1 = $comp['gradeformatted']; break;
                case '2ND_TERM': $term2 = $comp['gradeformatted']; break;
            }
        }
 
        $third_total = $category['gradeformatted'];
        $grade_3rd   = $category['lettergradeformatted'];
        $pos_3rd     = srl_format_position($category['rank'] ?? null);
        $cum         = srl_calc_cumulative($term1, $term2, $third_total);
 
        echo '<tr style="background:' . $bg . ';text-align:center;">
            <td style="padding:5px;text-align:left;border:1px solid #ddd;">' . esc_html($subject_name)  . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                 . esc_html($ca)            . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                 . esc_html($exam1)         . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                 . esc_html($exam2)         . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($third_total) . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($grade_3rd)   . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                 . esc_html($pos_3rd)       . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                 . esc_html($term1)         . '</td>
            <td style="padding:5px;border:1px solid #ddd;">'                 . esc_html($term2)         . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum['total']) . '</td>
            <td style="padding:5px;border:1px solid #ddd;font-weight:bold;">' . esc_html($cum['avg'])   . '</td>
        </tr>';
    }
 
    echo '</tbody></table></div>';
}

/** Detect JSS3 / SS3 exit classes */

public static function render_standard_subject_table($subjects) {
    foreach ($subjects as $subject_name => $subject_data) {
        $category = $subject_data['category'];
        $ca = $exam1 = $exam2 = '-';
 
        foreach ($subject_data['direct_mods'] as $mod) {
            $name = $mod['itemname'] ?? '';
            if (preg_match('/\s*-\s*CA\s*$/i', $name))          $ca    = $mod['gradeformatted'];
            if (preg_match('/\s*-\s*1st\s+Exam\s*$/i', $name))  $exam1 = $mod['gradeformatted'];
            if (preg_match('/\s*-\s*2nd\s+Exam\s*$/i', $name))  $exam2 = $mod['gradeformatted'];
        }
 
        echo '<div class="srl-subject-card">';
        echo '<div class="srl-subject-header">';
        echo '<span class="srl-subject-name">' . esc_html($subject_name) . '</span>';
        echo '<span class="srl-subject-total">Total: ' . esc_html($category['gradeformatted']) . '/' . esc_html($category['grademax'])
            . ' | Grade: ' . esc_html($category['lettergradeformatted'])
            . ' | Position: ' . esc_html(srl_format_position($category['rank'] ?? null)) . ' out of ' . esc_html($category['numusers'] ?? 'N/A') . '</span>';
        echo '</div>';
        echo '<div class="srl-subject-breakdown">';
        echo '<div class="srl-breakdown-item"><strong>CA:</strong> '       . esc_html($ca)    . '/20</div>';
        echo '<div class="srl-breakdown-item"><strong>1st Exam:</strong> ' . esc_html($exam1) . '/30</div>';
        echo '<div class="srl-breakdown-item"><strong>2nd Exam:</strong> ' . esc_html($exam2) . '/50</div>';
        echo '</div>';
        echo '</div>';
    }
}
 
 
// ============================================================
// NEW HELPER — component type matcher for 3rd term items
// ============================================================
 

}

// Backward-compatible wrappers: old function names still work.
function srl_display_grades($token, $moodle_endpoint, $student_id, $course_id) { return SRL_Web_Renderer::display_grades($token, $moodle_endpoint, $student_id, $course_id); }
function srl_render_exit_class_subject_table($subjects) { return SRL_Web_Renderer::render_exit_class_subject_table($subjects); }
function srl_render_standard_third_term_subject_table($subjects) { return SRL_Web_Renderer::render_standard_third_term_subject_table($subjects); }
function srl_render_third_term_subject_table($subjects) { return SRL_Web_Renderer::render_third_term_subject_table($subjects); }
function srl_render_standard_subject_table($subjects) { return SRL_Web_Renderer::render_standard_subject_table($subjects); }
