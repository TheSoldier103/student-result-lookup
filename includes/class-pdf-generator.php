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
        $pdf->SetMargins(10, 6, 10);
        $pdf->SetAutoPageBreak(true, 6);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->AddPage();

        if (file_exists($this->watermark_path)) {
            $pdf->SetAlpha(0.05);
            $pdf->Image($this->watermark_path, 35, 90, 140, 0, '', '', '', false, 300, '', false, false, 0, false, false, true);
            $pdf->SetAlpha(1);
        }

        // Draw the school logo directly with TCPDF. Putting a local image inside
        // writeHTML() can cause TCPDF to reserve an oversized table row.
        if (file_exists($this->logo_path)) {
            $pdf->Image(
                $this->logo_path,
                11,
                7,
                18,
                0,
                '',
                '',
                '',
                false,
                300,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
        }

        $is_third_term    = ($data['course_info']['term'] === '3rd Term');
        $is_exit_class    = $this->is_exit_class($data['course_info']['class']);

        $pdf->SetY(6);

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
        $id_number = $usergrade['useridnumber'] ?? ($student_data['idnumber'] ?? 'N/A');

        $html = '<div style="text-align:center;margin:0;padding:0;">'
            . '<div style="font-size:16px;font-weight:bold;line-height:17px;">PETRA CHRISTIAN ACADEMY</div>'
            . '<div style="font-size:6.8px;font-style:italic;font-weight:bold;line-height:8px;">Righteousness and Excellence</div>'
            . '<div style="font-size:6.2px;font-weight:bold;line-height:7px;">Telephone: +2348052755971, +2348166777788</div>'
            . '<div style="font-size:8.5px;font-weight:bold;line-height:10px;">STUDENT REPORT CARD</div>'
            . '</div>';

        $html .= '<table width="100%" cellpadding="1" cellspacing="0" style="font-size:6.3px;line-height:7.4px;border-collapse:collapse;margin-top:1px;">'
            . '<tr>'
            . '<td width="11%" style="border:1px solid #999;"><b>Name:</b></td>'
            . '<td width="27%" style="border:1px solid #999;">' . esc_html($usergrade['userfullname'] ?? 'N/A') . '</td>'
            . '<td width="13%" style="border:1px solid #999;"><b>ID Number:</b></td>'
            . '<td width="19%" style="border:1px solid #999;">' . esc_html($id_number) . '</td>'
            . '<td width="10%" style="border:1px solid #999;"><b>Class:</b></td>'
            . '<td width="20%" style="border:1px solid #999;">' . esc_html($course_info['class'] ?? 'N/A') . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="border:1px solid #999;"><b>Sex:</b></td>'
            . '<td style="border:1px solid #999;">' . esc_html($student_data['sex'] ?? 'N/A') . '</td>'
            . '<td style="border:1px solid #999;"><b>Session:</b></td>'
            . '<td style="border:1px solid #999;">' . esc_html($course_info['session'] ?? 'N/A') . '</td>'
            . '<td style="border:1px solid #999;"><b>Term:</b></td>'
            . '<td style="border:1px solid #999;">' . esc_html($course_info['term'] ?? 'N/A') . '</td>'
            . '</tr></table>';

        return $html;
    }

    private function render_attendance_and_grade_scale($organized, $announcements) {
        $html = '<table style="width:100%;margin-top:1px;margin-bottom:1px;"><tr>';

        $html .= '<td style="width:48%;vertical-align:top;">'
            . '<div style="background:#34495e;color:#fff;text-align:center;font-size:6.8px;font-weight:bold;line-height:8px;padding:1px;">ATTENDANCE</div>'
            . '<table width="100%" cellpadding="1" cellspacing="0" style="font-size:5.8px;line-height:6.8px;border-collapse:collapse;">'
            . '<tr><td bgcolor="#f5f5f5" style="border:1px solid #bbb;"><b>Days School Opened:</b></td>'
            . '<td style="text-align:center;border:1px solid #bbb;">' . ($announcements['days_opened'] ?? 'N/A') . '</td></tr>'
            . '<tr><td bgcolor="#f5f5f5" style="border:1px solid #bbb;"><b>Days Present:</b></td>'
            . '<td style="text-align:center;border:1px solid #bbb;">' . ($organized['attendance']['present'] ?? 'N/A') . '</td></tr>'
            . '</table></td>';

        $html .= '<td style="width:4%;"></td>';

        $html .= '<td style="width:48%;vertical-align:top;">'
            . '<div style="background:#34495e;color:#fff;text-align:center;font-size:6.8px;font-weight:bold;line-height:8px;padding:1px;">GRADE SCALE</div>'
            . '<table width="100%" cellpadding="1" cellspacing="0" style="font-size:5.6px;line-height:6.5px;border-collapse:collapse;">'
            . '<tr bgcolor="#ecf0f1">'
            . '<th style="text-align:center;border:1px solid #bbb;font-weight:bold;">GRADE</th>'
            . '<th style="text-align:center;border:1px solid #bbb;font-weight:bold;">RANGE</th>'
            . '<th style="text-align:center;border:1px solid #bbb;font-weight:bold;">REMARK</th>'
            . '</tr>';

        foreach ($this->grade_scale as $grade => $info) {
            $html .= '<tr>'
                . '<td style="text-align:center;border:1px solid #ccc;">' . $grade . '</td>'
                . '<td style="text-align:center;border:1px solid #ccc;">' . $info['min'] . '-' . $info['max'] . '</td>'
                . '<td style="text-align:center;border:1px solid #ccc;">' . $info['remark'] . '</td>'
                . '</tr>';
        }

        $html .= '</table></td></tr></table>';
        return $html;
    }
    private function render_remarks($organized, $staff) {
        $teacher_remark = trim((string)($organized['remarks']['teacher'] ?? ''));
        $principal_remark = trim((string)($organized['remarks']['principal'] ?? ''));

        if ($teacher_remark === '' && $principal_remark === '') return '';

        $teacher_name = trim((string)($staff['teacher'] ?? ''));
        $principal_name = trim((string)($staff['principal'] ?? ''));

        $html = '<div style="background:#34495e;color:#fff;text-align:center;font-size:7px;font-weight:bold;line-height:8px;padding:1px;">REMARKS</div>';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1px;"><tr>';

        if ($teacher_remark !== '') {
            $width = $principal_remark !== '' ? 49 : 100;
            $html .= '<td width="' . $width . '%" style="vertical-align:top;">'
                . '<table width="100%" cellpadding="2" cellspacing="0" style="border:1px solid #c7d3df;background:#f7f9fb;">'
                . '<tr><td style="font-size:6.2px;font-weight:bold;color:#2c3e50;">CLASS TEACHER</td></tr>'
                . '<tr><td style="font-size:5.9px;line-height:6.9px;color:#222;">' . wp_kses_post($teacher_remark);
            if ($teacher_name !== '') {
                $html .= '<br><span style="font-size:5.5px;font-style:italic;">Signed by: ' . esc_html($teacher_name) . '</span>';
            }
            $html .= '</td></tr></table></td>';
        }

        if ($teacher_remark !== '' && $principal_remark !== '') {
            $html .= '<td width="2%"></td>';
        }

        if ($principal_remark !== '') {
            $width = $teacher_remark !== '' ? 49 : 100;
            $html .= '<td width="' . $width . '%" style="vertical-align:top;">'
                . '<table width="100%" cellpadding="2" cellspacing="0" style="border:1px solid #c7d3df;background:#f7f9fb;">'
                . '<tr><td style="font-size:6.2px;font-weight:bold;color:#2c3e50;">PRINCIPAL / VICE-PRINCIPAL</td></tr>'
                . '<tr><td style="font-size:5.9px;line-height:6.9px;color:#222;">' . wp_kses_post($principal_remark);
            if ($principal_name !== '') {
                $html .= '<br><span style="font-size:5.5px;font-style:italic;">Signed by: ' . esc_html($principal_name) . '</span>';
            }
            $html .= '</td></tr></table></td>';
        }

        $html .= '</tr></table>';
        return $html;
    }
    private function render_announcements($announcements) {
        if (empty($announcements)) return '';

        $items = [];
        if (!empty($announcements['next_term'])) $items[] = '<b>Next Term Begins:</b> ' . esc_html($announcements['next_term']);
        if (!empty($announcements['fees'])) $items[] = '<b>Fees for Next Term:</b> ' . esc_html($announcements['fees']);
        if (!empty($announcements['general'])) $items[] = '<b>Announcement:</b> ' . wp_kses_post($announcements['general']);

        if (!$items) return '';

        return '<div style="margin-top:1px;font-size:5.8px;line-height:6.8px;border-top:1px solid #bbb;padding-top:1px;">'
            . implode(' &nbsp; | &nbsp; ', $items)
            . '</div>';
    }
    private function render_footer() {
        return '<div style="margin-top:1px;border-top:1px solid #bbb;padding-top:1px;text-align:center;font-size:5.3px;line-height:6px;">'
            . 'Petra Christian Academy &bull; Generated on ' . date_i18n('F j, Y')
            . '</div>';
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