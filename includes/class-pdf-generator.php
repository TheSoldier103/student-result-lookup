<?php
/**
 * Professional PDF Generator
 * Supports 1st/2nd term (standard) and 3rd term (extended: standard + JSS3/SS3 simplified)
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'class-grade-organizer.php';
require_once plugin_dir_path(__FILE__) . 'class-ranking-repository.php';
require_once plugin_dir_path(__FILE__) . 'renderers/class-standard-term-pdf-renderer.php';
require_once plugin_dir_path(__FILE__) . 'renderers/class-third-term-pdf-renderer.php';

class SRL_PDF_Generator {
    use SRL_Standard_Term_PDF_Renderer;
    use SRL_Third_Term_PDF_Renderer;

    private $logo_path;
    private $watermark_path;
    private $grade_scale;

    public function __construct() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));

        if (!defined('K_PATH_CACHE')) {
            $cache_dir = $plugin_dir . 'cache/';
            if (!file_exists($cache_dir)) @mkdir($cache_dir, 0755, true);
            define('K_PATH_CACHE', $cache_dir);
        }

        if (file_exists($plugin_dir . 'vendor/autoload.php')) {
            require_once($plugin_dir . 'vendor/autoload.php');
        } elseif (file_exists($plugin_dir . 'vendor/tcpdf/tcpdf.php')) {
            require_once($plugin_dir . 'vendor/tcpdf/tcpdf.php');
        } else {
            wp_die('TCPDF library not found.');
        }

        $this->logo_path = $plugin_dir . 'assets/logo.jpg';
        if (!file_exists($this->logo_path)) {
            $this->logo_path = $plugin_dir . 'assets/logo.png';
        }

        $this->watermark_path = $plugin_dir . 'assets/logo.png';
        if (!file_exists($this->watermark_path)) {
            $this->watermark_path = $plugin_dir . 'assets/logo.jpg';
        }

        $this->grade_scale = include($plugin_dir . 'includes/grade-scale-config.php');
    }

    // =========================================================================
    // PUBLIC ENTRY POINT
    // =========================================================================

    public function generate_report_card($data) {
        while (ob_get_level()) ob_end_clean();
        ob_start();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Petra Christian Academy');
        $pdf->SetAuthor('Petra Christian Academy');
        $pdf->SetTitle('Report Card - ' . $data['grades_data']['usergrades'][0]['userfullname']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->AddPage();

        if (file_exists($this->watermark_path)) {
            $pdf->SetAlpha(0.05);
            $pdf->Image($this->watermark_path, 35, 90, 140, 0, '', '', '', false, 300, '', false, false, 0, false, false, true);
            $pdf->SetAlpha(1);
        }

        $is_third_term    = ($data['course_info']['term'] === '3rd Term');
        $is_exit_class    = $this->is_exit_class($data['course_info']['class']);

        $pdf->SetY(10);

        if ($is_third_term) {
            $this->generate_third_term_pdf($pdf, $data, $is_exit_class);
        } else {
            $html = $this->generate_standard_html($data);
            $pdf->writeHTML($html, true, false, true, false, '');
        }

        $filename = $this->generate_filename($data['grades_data']['usergrades'][0]['userfullname']);
        ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }

    // =========================================================================
    // TERM / CLASS DETECTION
    // =========================================================================

    /**
     * JSS3 and SS3 classes use the simplified layout (no CA/Exam columns).
     * Matches: JSS3, SS3, SS3 Art, SS3 Science, etc.
     */
    private function is_exit_class($class) {
        return SRL_Grade_Organizer::is_exit_class($class);
    }

    // =========================================================================
    // GRADE SCALE HELPER
    // =========================================================================

    private function derive_grade($average) {
        return SRL_Grade_Organizer::derive_grade($average);
    }

    // =========================================================================
    // GRADE ITEM ORGANIZER  (shared by PDF + web view via static helper)
    // =========================================================================

    /**
     * Organizes raw Moodle grade items into a structured array.
     *
     * For 3rd term, the structure per subject is:
     *   Parent category  (itemname: "Subject Name", grademax: 300, has rank = cum. position)
     *     ├─ mod  "Subject - 1st Term Total"  (categoryid = parent instance)
     *     ├─ mod  "Subject - 2nd Term Total"  (categoryid = parent instance)
     *     └─ named sub-category "Subject - 3rd Term"  (grademax: 100, has rank = 3rd term position)
     *          ├─ mod "Subject - CA"
     *          ├─ mod "Subject - 1st Exam"
     *          └─ mod "Subject - 2nd Exam"
     *
     * For JSS3/SS3 3rd term, the "- 3rd Term Total" is a mod directly under the parent
     * (no sub-category), same as 1st/2nd term totals.
     */
    public function organize_grade_items($gradeitems) {
        return SRL_Grade_Organizer::organize_grade_items($gradeitems);
    }

    // =========================================================================
    // COMPONENT EXTRACTORS
    // =========================================================================

    /**
     * From direct_mods, extract 1st Term Total, 2nd Term Total, and (for JSS3/SS3)
     * 3rd Term Total.
     */
    private function extract_term_totals($direct_mods, $subject_name) {
        return SRL_Grade_Organizer::extract_term_totals($direct_mods);
    }

    /**
     * From third_subcat_mods, extract CA, 1st Exam, 2nd Exam.
     */
    private function extract_third_term_components($third_subcat_mods) {
        return SRL_Grade_Organizer::extract_third_term_components($third_subcat_mods);
    }

    /**
     * Compute cumulative average from term scores.
     * Terms sat = count of terms where graderaw !== null.
     * Returns ['avg' => float|null, 'terms_sat' => int]
     */
    private function calc_cum_avg($term_scores, $third_subcat_graderaw) {
        return SRL_Grade_Organizer::calc_cum_avg($term_scores, $third_subcat_graderaw);
    }

    // =========================================================================
    // 3RD TERM PDF
    // =========================================================================


    // -------------------------------------------------------------------------
    // Standard 3rd term subject table (JSS1/JSS2/SS1/SS2)
    //
    // Columns:
    //   Subject | 1st Term | 2nd Term | CA(20) | 1st Exam(30) | 2nd Exam(50) |
    //   3rd Total(100) | [CUMULATIVE sub-header] Cum.Total | Cum.Avg | Cum.Grade | Cum.Pos
    //
    // Rotated: 1st Term, 2nd Term, Cum.Total, Cum.Avg, Cum.Grade, Cum.Pos
    // -------------------------------------------------------------------------


    // -------------------------------------------------------------------------
    // Exit class (JSS3/SS3) 3rd term subject table
    //
    // Columns:
    //   Subject | 1st Term | 2nd Term | 3rd Term Total(100) |
    //   [CUMULATIVE] Cum.Total | Cum.Avg | Cum.Grade | Cum.Pos
    //
    // Rotated: 1st Term, 2nd Term, Cum.Total, Cum.Avg, Cum.Grade, Cum.Pos
    // -------------------------------------------------------------------------


    // -------------------------------------------------------------------------
    // Shared table drawing primitives
    // -------------------------------------------------------------------------

    private function draw_header_row($pdf, $cols, $lm, $y, $header_h, $header_bg, $column_header_colors = []) {
        $x = $lm;
        foreach ($cols as $i => [$label, $w, $rotated]) {
            $cell_bg = $column_header_colors[$i] ?? $header_bg;
            $pdf->SetFillColor(...$cell_bg);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x, $y, $w, $header_h, 'FD');

            if ($rotated) {
                $cx = $x + $w / 2;
                $cy = $y + $header_h / 2;
                $pdf->StartTransform();
                $pdf->Rotate(90, $cx, $cy);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 6);
                $pdf->SetXY($cx - $header_h / 2, $cy - $w / 2);
                $pdf->MultiCell($header_h, $w, str_replace('\n', "\n", $label), 0, 'C', false, 0);
                $pdf->StopTransform();
            } else {
                $lines      = explode('\n', $label);
                $line_h     = 3.5;
                $total_text = count($lines) * $line_h;
                $text_y     = $y + ($header_h - $total_text) / 2;
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 6);
                foreach ($lines as $line) {
                    $pdf->SetXY($x, $text_y);
                    $pdf->Cell($w, $line_h, $line, 0, 0, 'C', false);
                    $text_y += $line_h;
                }
            }
            $x += $w;
        }
    }

    private function draw_data_row($pdf, $cols, $lm, $row_values, $row_h, $bg, $bold_indices) {
        $pdf->SetFillColor(...$bg);
        $pdf->SetDrawColor(180, 180, 180);
        $x = $lm;

        foreach ($cols as $i => [$label, $w, $rotated]) {
            $align = ($i === 0) ? 'L' : 'C';
            $bold  = in_array($i, $bold_indices);
            $pdf->SetFont('dejavusans', $bold ? 'B' : '', 7);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($x, $pdf->GetY());
            $pdf->Cell($w, $row_h, $row_values[$i], 1, 0, $align, true);
            $x += $w;
        }
        $pdf->Ln($row_h);
    }

    // =========================================================================
    // 3RD TERM PERFORMANCE SUMMARY (6 boxes)
    // =========================================================================


    /**
     * Sum up cum totals and avgs across all subjects for the performance summary boxes.
     * For exit classes the 3rd term sub-cat is null; we use term3 graderaw instead.
     */


    // =========================================================================
    // STANDARD TERM PDF (1st / 2nd)
    // =========================================================================


    // =========================================================================
    // SHARED HTML RENDER HELPERS
    // =========================================================================

    private function render_page_header($usergrade, $student_data, $course_info) {
        $html = '<table style="width:100%; border-bottom:2px solid #000; padding-bottom:3px; margin-bottom:3px;"><tr>';

        if (file_exists($this->logo_path)) {
            $image_data = base64_encode(file_get_contents($this->logo_path));
            $html .= '<td style="width:18%;"><img src="@' . $image_data . '" style="width:50px;" /></td>';
        } else {
            $html .= '<td style="width:18%;"></td>';
        }

        $html .= '<td style="width:82%; text-align:center;">
            <h1 style="font-size:16px; font-weight:bold; margin:0;">PETRA CHRISTIAN ACADEMY</h1>
            <p style="font-size:8px; line-height:5px; font-weight:bold; font-style:italic; margin:1px 0;">Righteousness and Excellence</p>
            <p style="font-size:8px; line-height:5px; font-weight:bold; margin:1px 0;">Telephone: +2348052755971, +2348166777788</p>
            <p style="font-size:9px; font-weight:bold; margin:2px 0;">STUDENT REPORT CARD</p>
        </td></tr></table>';

        $html .= $this->spacer(2);

        $html .= '<table style="width:100%; background-color:#f5f5f5; border:1px solid #000; margin-top:3px; margin-bottom:3px; font-size:7px;">
            <tr>
                <td style="width:12%; padding:5px 3px; border-right:1px solid #ccc;"><strong>Name:</strong></td>
                <td style="width:38%; padding:5px 3px; border-right:1px solid #ccc;">' . htmlspecialchars($usergrade['userfullname']) . '</td>
                <td style="width:12%; padding:5px 3px; border-right:1px solid #ccc;"><strong>Class:</strong></td>
                <td style="width:13%; padding:5px 3px; border-right:1px solid #ccc;">' . htmlspecialchars($course_info['class']) . '</td>
                <td style="width:12%; padding:5px 3px; border-right:1px solid #ccc;"><strong>Term:</strong></td>
                <td style="width:13%; padding:5px 3px;">' . htmlspecialchars($course_info['term']) . '</td>
            </tr>
            <tr>
                <td style="padding:5px 3px; border-right:1px solid #ccc; border-top:1px solid #ccc;"><strong>Sex:</strong></td>
                <td style="padding:5px 3px; border-right:1px solid #ccc; border-top:1px solid #ccc;">' . htmlspecialchars($student_data['sex']) . '</td>
                <td style="padding:5px 3px; border-right:1px solid #ccc; border-top:1px solid #ccc;"><strong>Session:</strong></td>
                <td colspan="3" style="padding:5px 3px; border-top:1px solid #ccc;">' . htmlspecialchars($course_info['session']) . '</td>
            </tr>
        </table>';

        return $html;
    }

    private function render_attendance_and_grade_scale($organized, $announcements) {
        $html = '<table style="width:100%; margin-top:2px; margin-bottom:2px;"><tr>';

        $html .= '<td style="width:48%; vertical-align:top;">';
        $html .= $this->section_header('Attendance');
        $html .= $this->spacer(1);
        $html .= '<table width="100%" cellpadding="1" cellspacing="0" style="font-size:6.5px; font-weight:normal;">
            <tr>
                <td bgcolor="#f5f5f5" style="border:1px solid #ccc;"><b>Days School Opened:</b></td>
                <td style="text-align:center; border:1px solid #ccc;">' . ($announcements['days_opened'] ?? 'N/A') . '</td>
            </tr>
            <tr>
                <td bgcolor="#f5f5f5" style="border:1px solid #ccc;"><b>Days Present:</b></td>
                <td style="text-align:center; border:1px solid #ccc;">' . ($organized['attendance']['present'] ?? 'N/A') . '</td>
            </tr>
        </table></td>';

        $html .= '<td style="width:4%;"></td>';

        $html .= '<td style="width:48%; vertical-align:top;">';
        $html .= $this->section_header('Grade Scale');
        $html .= $this->spacer(1);
        $html .= '<table width="100%" cellpadding="1" cellspacing="0" style="font-size:6.2px; font-weight:normal;">
            <tr bgcolor="#ecf0f1">
                <th style="text-align:center; border:1px solid #000; font-weight:normal;">GRADE</th>
                <th style="text-align:center; border:1px solid #000; font-weight:normal;">RANGE</th>
                <th style="text-align:center; border:1px solid #000; font-weight:normal;">REMARK</th>
            </tr>';
        foreach ($this->grade_scale as $grade => $info) {
            $html .= '<tr>
                <td style="text-align:center; border:1px solid #ccc; font-weight:normal;">' . $grade . '</td>
                <td style="text-align:center; border:1px solid #ccc; font-weight:normal;">' . $info['min'] . '-' . $info['max'] . '</td>
                <td style="text-align:center; border:1px solid #ccc; font-weight:normal;">' . $info['remark'] . '</td>
            </tr>';
        }
        $html .= '</table></td></tr></table>';

        return $html;
    }

    private function render_remarks($organized, $staff) {
        $html  = $this->section_header('Remarks');
        $html .= $this->spacer(2);
        $html .= '<table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="border-left:4px solid #3498db; padding:6px; font-size:8px;">
                <strong>Class Teacher:</strong> ' . strip_tags($organized['remarks']['teacher']) . '
                <em>(Signed by: ' . $staff['teacher'] . ')</em>
            </td>
        </tr>
        <tr>
            <td style="border-left:4px solid #e74c3c; padding:6px; font-size:8px;">
                <strong>Principal/Vice-Principal:</strong> ' . strip_tags($organized['remarks']['principal']) . '
                <em>(Signed by: ' . $staff['principal'] . ')</em>
            </td>
        </tr>
        </table>';
        return $html;
    }

    private function render_announcements($announcements) {
        if (empty($announcements['next_term']) && empty($announcements['fees']) && empty($announcements['general'])) {
            return '';
        }
        $html  = $this->section_header('Announcements');
        $html .= $this->spacer(2);
        if ($announcements['next_term']) $html .= '<p style="margin:2px 0;"><strong>Next Term Begins:</strong> '   . $announcements['next_term'] . '</p>';
        if ($announcements['fees'])      $html .= '<p style="margin:2px 0;"><strong>Fees for Next Term:</strong> ' . $announcements['fees']      . '</p>';
        if ($announcements['general'])   $html .= '<p style="margin:2px 0;"><strong>Announcement:</strong> '       . strip_tags($announcements['general']) . '</p>';
        $html .= $this->spacer(6);
        return $html;
    }

    private function render_footer() {
        return '<div style="margin-top:2px; border-top:1px solid #333; padding-top:1px; text-align:center; font-size:6.5px; line-height:8px;">
            Petra Christian Academy • Generated on ' . date('F j, Y') . '
        </div>';
    }

    // =========================================================================
    // TINY HELPERS
    // =========================================================================

    private function section_header($title) {
        return '<table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td bgcolor="#34495e" style="height:22px; line-height:24px; text-align:center; font-weight:bold; color:#ffffff; font-size:10px;">
                ' . strtoupper($title) . '
            </td>
        </tr></table>';
    }

    private function spacer($h = 8) {
        return '<table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td style="height:' . $h . 'px; line-height:' . $h . 'px;"></td>
        </tr></table>';
    }

    private function format_position($number) {
        return SRL_Grade_Organizer::format_position($number);
    }

    private function generate_filename($student_name) {
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $student_name);
        return $clean . '_Report_Card_' . date('Y-m-d') . '.pdf';
    }

    private function get_styles() {
        return '<style>
            body  { font-family: dejavusans, sans-serif; font-size: 8px; color: #000; }
            h1    { font-size: 16px; margin: 0; }
            table { border-collapse: collapse; }
        </style>';
    }
}