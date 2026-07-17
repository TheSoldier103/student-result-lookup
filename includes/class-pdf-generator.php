<?php
/**
 * Professional PDF Generator
 * Supports 1st/2nd term (standard) and 3rd term (extended: standard + JSS3/SS3 simplified)
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'class-grade-organizer.php';

class SRL_PDF_Generator {

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

    private function generate_third_term_pdf($pdf, $data, $is_exit_class) {
        $usergrade     = $data['grades_data']['usergrades'][0];
        $student_data  = $data['student_data'];
        $course_info   = $data['course_info'];
        $announcements = $data['announcements'];
        $staff         = $data['staff'];

        $organized = $this->organize_grade_items($usergrade['gradeitems']);

        $html  = $this->get_styles();
        $html .= $this->render_page_header($usergrade, $student_data, $course_info);
        $html .= $this->spacer(4);
        $html .= $this->render_third_term_performance_summary($organized, $is_exit_class);
        $html .= $this->spacer(8);
        $html .= $this->render_attendance_and_grade_scale($organized, $announcements);
        $html .= $this->spacer(8);

        $pdf->writeHTML($html, true, false, true, false, '');

        // Subject table: mixed direct-draw (rotated headers) + cell rows
        if ($is_exit_class) {
            $this->draw_exit_class_subject_table($pdf, $organized);
        } else {
            $this->draw_standard_third_term_subject_table($pdf, $organized);
        }

        $html2  = $this->spacer(8);
        $html2 .= $this->render_remarks($organized, $staff);
        $html2 .= $this->spacer(8);
        $html2 .= $this->render_announcements($announcements);
        $html2 .= $this->render_footer();

        $pdf->writeHTML($html2, true, false, true, false, '');
    }

    // -------------------------------------------------------------------------
    // Standard 3rd term subject table (JSS1/JSS2/SS1/SS2)
    //
    // Columns:
    //   Subject | 1st Term | 2nd Term | CA(20) | 1st Exam(30) | 2nd Exam(50) |
    //   3rd Total(100) | [CUMULATIVE sub-header] Cum.Total | Cum.Avg | Cum.Grade | Cum.Pos
    //
    // Rotated: 1st Term, 2nd Term, Cum.Total, Cum.Avg, Cum.Grade, Cum.Pos
    // -------------------------------------------------------------------------

    private function draw_standard_third_term_subject_table($pdf, $organized) {
        $complete = SRL_Grade_Organizer::has_complete_term_history($organized['subjects']);
        $pdf->SetFont('dejavusans', '', 6.2);
        $lm = $pdf->GetX();
        $y  = $pdf->GetY();
        $header_bg = [52, 73, 94];
        $subheader_h = 6;
        $header_h = 22;

        if ($complete) {
            // Fits within the 190 mm printable width in portrait while keeping
            // compact/rotated headers for the dense cumulative report.
            $cols = [
                ['SUBJECT', 38, false],
                ['CA\n(20)', 12, false],
                ['1ST EXAM\n(30)', 14, false],
                ['2ND EXAM\n(50)', 14, false],
                ['TOTAL\n(100)', 14, false],
                ['GRADE', 11, false],
                ['POSITION', 14, false],
                ['1ST TERM\n(100)', 16, false],
                ['2ND TERM\n(100)', 16, false],
                ['TOTAL\n(300)', 17, false],
                ['GRADE', 11, false],
                ['POSITION', 13, false],
            ];

            $groups = [
                ['label' => '', 'start' => 0, 'count' => 1, 'bg' => $header_bg],
                ['label' => '3RD TERM', 'start' => 1, 'count' => 6, 'bg' => [52, 73, 94]],
                ['label' => 'PRIOR TERMS', 'start' => 7, 'count' => 2, 'bg' => [93, 109, 126]],
                ['label' => 'CUMULATIVE', 'start' => 9, 'count' => 3, 'bg' => [8, 60, 120]],
            ];

            foreach ($groups as $group) {
                $x = $lm;
                for ($i = 0; $i < $group['start']; $i++) $x += $cols[$i][1];
                $w = 0;
                for ($i = $group['start']; $i < $group['start'] + $group['count']; $i++) $w += $cols[$i][1];
                $pdf->SetXY($x, $y);
                $pdf->SetFillColor(...$group['bg']);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 6.2);
                $pdf->Cell($w, $subheader_h, $group['label'], 1, 0, 'C', true);
            }

            // Expand to the full printable width and centre the table.
            $table_w = 0;
            foreach ($cols as $col) $table_w += $col[1];
            $lm = ($pdf->getPageWidth() - $table_w) / 2;

            // Redraw grouped headers using the centred left margin.
            foreach ($groups as $group) {
                $x = $lm;
                for ($i = 0; $i < $group['start']; $i++) $x += $cols[$i][1];
                $w = 0;
                for ($i = $group['start']; $i < $group['start'] + $group['count']; $i++) $w += $cols[$i][1];
                $pdf->SetXY($x, $y);
                $pdf->SetFillColor(...$group['bg']);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 6.2);
                $pdf->Cell($w, $subheader_h, $group['label'], 1, 0, 'C', true);
            }

            $y += $subheader_h;
            $header_h = 14;
            $this->draw_header_row($pdf, $cols, $lm, $y, $header_h, $header_bg);
        } else {
            $cols = [
                ['SUBJECT', 65, false],
                ['CA\n(20)', 20, false],
                ['1ST EXAM\n(30)', 23, false],
                ['2ND EXAM\n(50)', 23, false],
                ['TOTAL\n(100)', 22, false],
                ['GRADE', 17, false],
                ['POSITION', 20, false],
            ];
            $table_w = 0;
            foreach ($cols as $col) $table_w += $col[1];
            $lm = ($pdf->getPageWidth() - $table_w) / 2;
            $header_h = 14;
            $this->draw_header_row($pdf, $cols, $lm, $y, $header_h, $header_bg);
        }

        $pdf->SetXY($lm, $y + $header_h);
        $pdf->SetTextColor(0, 0, 0);
        $this->draw_standard_third_term_rows($pdf, $organized, $cols, $lm, $complete);

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetY($pdf->GetY() + 2);
    }

    private function draw_standard_third_term_rows($pdf, $organized, $cols, $lm, $complete) {
        $row_h = 6;
        $row_num = 0;

        foreach ($organized['subjects'] as $subject_name => $subject_data) {
            $row_num++;
            $bg = ($row_num % 2 === 0) ? [249, 249, 249] : [255, 255, 255];
            $terms = $this->extract_term_totals($subject_data['direct_mods'] ?? [], $subject_name);
            $components = $this->extract_third_term_components($subject_data['third_subcat_mods'] ?? []);
            $third = $subject_data['third_subcat'] ?? null;
            $parent = $subject_data['category'] ?? [];

            $third_total = $third['gradeformatted'] ?? '-';
            $third_grade = (!empty($third['lettergradeformatted']) && $third['lettergradeformatted'] !== '-')
                ? $third['lettergradeformatted']
                : $this->derive_grade($third['graderaw'] ?? null);
            $third_pos = (($third['rank'] ?? 0) > 0) ? $this->format_position($third['rank']) : 'N/A';

            $row_values = [
                $subject_name,
                $components['ca'],
                $components['exam1'],
                $components['exam2'],
                $third_total,
                $third_grade,
                $third_pos,
            ];

            if ($complete) {
                $cum_avg = SRL_Grade_Organizer::normalized_percentage($parent);
                $cum_grade = (!empty($parent['lettergradeformatted']) && $parent['lettergradeformatted'] !== '-')
                    ? $parent['lettergradeformatted']
                    : $this->derive_grade($cum_avg);
                $cum_pos = (($parent['rank'] ?? 0) > 0) ? $this->format_position($parent['rank']) : 'N/A';
                $row_values = array_merge($row_values, [
                    $terms['term1']['formatted'],
                    $terms['term2']['formatted'],
                    $parent['gradeformatted'] ?? '-',
                    $cum_grade,
                    $cum_pos,
                ]);
            }

            $bold_indices = $complete ? [4, 7, 10, 11, 12, 13] : [4, 7];
            $this->draw_data_row($pdf, $cols, $lm, $row_values, $row_h, $bg, $bold_indices);
        }
    }

    // -------------------------------------------------------------------------
    // Exit class (JSS3/SS3) 3rd term subject table
    //
    // Columns:
    //   Subject | 1st Term | 2nd Term | 3rd Term Total(100) |
    //   [CUMULATIVE] Cum.Total | Cum.Avg | Cum.Grade | Cum.Pos
    //
    // Rotated: 1st Term, 2nd Term, Cum.Total, Cum.Avg, Cum.Grade, Cum.Pos
    // -------------------------------------------------------------------------

    private function draw_exit_class_subject_table($pdf, $organized) {
        $pdf->SetFont('dejavusans', '', 7);
        $lm = $pdf->GetX();
        $y  = $pdf->GetY();

        $cols = [
            ['SUBJECT',              55, false],
            ['1ST TERM\n(100)',      15, true],
            ['2ND TERM\n(100)',      15, true],
            ['3RD TERM\nTOTAL\n(100)', 20, false],
            ['CUM.\nTOTAL',         18, true],
            ['CUM.\nAVG',           18, true],
            ['CUM.\nGRADE',         18, true],
            ['CUM.\nPOSITION',      21, true],
        ];

        $header_h  = 20;
        $header_bg = [52, 73, 94];

        // "CUMULATIVE" sub-header over last 4 columns
        $cum_col_start = 4;
        $cum_x = $lm;
        foreach (array_slice($cols, 0, $cum_col_start) as [, $w]) $cum_x += $w;
        $cum_w = 0;
        foreach (array_slice($cols, $cum_col_start) as [, $w]) $cum_w += $w;

        $subheader_h = 6;

        $pdf->SetFillColor(8, 60, 120);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 6.5);
        $pdf->SetXY($cum_x, $y);
        $pdf->Cell($cum_w, $subheader_h, 'CUMULATIVE', 1, 0, 'C', true);

        $non_cum_w = 0;
        foreach (array_slice($cols, 0, $cum_col_start) as [, $w]) $non_cum_w += $w;
        $pdf->SetXY($lm, $y);
        $pdf->SetFillColor(...$header_bg);
        $pdf->Cell($non_cum_w, $subheader_h, '', 1, 0, 'C', true);

        $y += $subheader_h;

        $this->draw_header_row($pdf, $cols, $lm, $y, $header_h, $header_bg);
        $pdf->SetXY($lm, $y + $header_h);
        $pdf->SetTextColor(0, 0, 0);

        // Data rows
        $row_h  = 6;
        $row_num = 0;

        foreach ($organized['subjects'] as $subject_name => $subject_data) {
            $row_num++;
            $bg = ($row_num % 2 === 0) ? [249, 249, 249] : [255, 255, 255];

            $terms = $this->extract_term_totals($subject_data['direct_mods'], $subject_name);

            // For JSS3/SS3 the 3rd term total is a direct mod (term3), not a sub-category
            $third_total_fmt  = $terms['term3']['formatted'];
            $third_graderaw   = $terms['term3']['graderaw'];

            $parent        = $subject_data['category'];
            $cum_total_fmt = $parent['gradeformatted'];
            $cum_pos       = (($parent['rank'] ?? 0) > 0)
                                 ? $this->format_position($parent['rank'])
                                 : 'N/A';

            $cum_avg_data  = $this->calc_cum_avg($terms, $third_graderaw);
            $cum_avg_fmt   = $cum_avg_data['avg'] !== null
                                 ? number_format($cum_avg_data['avg'], 2)
                                 : '-';
            $cum_grade     = $cum_avg_data['avg'] !== null
                                 ? $this->derive_grade($cum_avg_data['avg'])
                                 : '-';

            $row_values = [
                $subject_name,
                $terms['term1']['formatted'],
                $terms['term2']['formatted'],
                $third_total_fmt,
                $cum_total_fmt,
                $cum_avg_fmt,
                $cum_grade,
                $cum_pos,
            ];

            $this->draw_data_row($pdf, $cols, $lm, $row_values, $row_h, $bg, [3, 4, 5, 6, 7]);
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetY($pdf->GetY() + 2);
    }

    // -------------------------------------------------------------------------
    // Shared table drawing primitives
    // -------------------------------------------------------------------------

    private function draw_header_row($pdf, $cols, $lm, $y, $header_h, $header_bg) {
        $x = $lm;
        foreach ($cols as [$label, $w, $rotated]) {
            $pdf->SetFillColor(...$header_bg);
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

    private function render_third_term_performance_summary($organized, $is_exit_class = false) {
        $total = $organized['course_total'];
        if (!$total) return '';

        // Keep the existing exit-class behavior untouched until the dedicated
        // JSS3/SS3 pass requested after the standard 3rd-term fixes.
        if ($is_exit_class) {
            $position   = $this->format_position($total['rank'] ?? null) . ' out of ' . ($total['numusers'] ?? 'N/A');
            $obtained   = $total['gradeformatted'];
            $obtainable = $total['grademax'];
            $pct        = $total['percentageformatted'];
            $cum = $this->calc_overall_cumulative_summary($organized['subjects']);
            $boxes = [
                ['#003580', 'POSITION (3RD)',         $position],
                ['#5d2d91', 'TOTAL OBTAINED (3RD)',   $obtained],
                ['#0072bc', 'TOTAL OBTAINABLE (3RD)', $obtainable],
                ['#008a76', 'PERCENTAGE (3RD)',       $pct],
                ['#b5451b', 'CUMULATIVE TOTAL',       $cum['total']],
                ['#7d6608', 'CUMULATIVE AVERAGE',     $cum['avg']],
            ];
            return $this->render_summary_boxes('Performance Summary', $boxes);
        }

        $complete = SRL_Grade_Organizer::has_complete_term_history($organized['subjects']);
        $third = SRL_Grade_Organizer::calc_third_term_summary($organized['subjects']);

        $html  = $this->section_header('Performance Summary');
        $html .= $this->spacer(3);
        $html .= '<div style="font-size:8px;font-weight:bold;color:#2c3e50;margin-bottom:2px;">3RD TERM</div>';
        $html .= $this->render_summary_box_row([
            ['#003580', 'POSITION', 'N/A'],
            ['#5d2d91', 'TOTAL OBTAINED', $third['obtained']],
            ['#0072bc', 'TOTAL OBTAINABLE', $third['obtainable']],
            ['#008a76', 'PERCENTAGE', $third['percentage']],
        ]);

        if ($complete) {
            $position = $this->format_position($total['rank'] ?? null) . ' out of ' . ($total['numusers'] ?? 'N/A');
            $cum_avg = SRL_Grade_Organizer::normalized_percentage($total);
            $html .= $this->spacer(4);
            $html .= '<div style="font-size:8px;font-weight:bold;color:#2c3e50;margin-bottom:2px;">CUMULATIVE</div>';
            $html .= $this->render_summary_box_row([
                ['#003580', 'POSITION', $position],
                ['#5d2d91', 'TOTAL OBTAINED', $total['gradeformatted']],
                ['#0072bc', 'TOTAL OBTAINABLE', $total['grademax']],
                ['#008a76', 'PERCENTAGE', $total['percentageformatted']],
            ]);
        }

        return $html;
    }

    private function render_summary_box_row($boxes) {
        $width = 100 / max(1, count($boxes));
        $html = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>';

        foreach ($boxes as [$bg, $label, $value]) {
            $html .= '<td width="' . $width . '%" bgcolor="' . $bg . '" '
                . 'style="border:1px solid #000; color:#ffffff; text-align:center; vertical-align:middle;">'
                . '<table width="100%" cellpadding="0" cellspacing="0">'
                . '<tr><td height="15" align="center" valign="middle" '
                . 'style="color:#ffffff; font-size:6.5px; font-weight:bold; line-height:8px;">'
                . $label
                . '</td></tr>'
                . '<tr><td height="23" align="center" valign="middle" '
                . 'style="color:#ffffff; font-size:11px; font-weight:bold; line-height:13px;">'
                . $value
                . '</td></tr>'
                . '</table></td>';
        }

        return $html . '</tr></table>';
    }

    private function render_summary_boxes($title, $boxes) {
        $html = $this->section_header($title) . $this->spacer(3);
        return $html . $this->render_summary_box_row($boxes);
    }

    /**
     * Sum up cum totals and avgs across all subjects for the performance summary boxes.
     * For exit classes the 3rd term sub-cat is null; we use term3 graderaw instead.
     */
    private function calc_overall_cumulative_summary($subjects) {
        return SRL_Grade_Organizer::calc_overall_cumulative_summary($subjects);
    }

    // =========================================================================
    // STANDARD TERM PDF (1st / 2nd)
    // =========================================================================

    private function generate_standard_html($data) {
        $usergrade     = $data['grades_data']['usergrades'][0];
        $student_data  = $data['student_data'];
        $course_info   = $data['course_info'];
        $announcements = $data['announcements'];
        $staff         = $data['staff'];

        $organized = $this->organize_grade_items($usergrade['gradeitems']);

        $html  = $this->get_styles();
        $html .= $this->render_page_header($usergrade, $student_data, $course_info);
        $html .= $this->spacer(4);
        $html .= $this->render_standard_performance_summary($organized);
        $html .= $this->spacer(10);
        $html .= $this->render_attendance_and_grade_scale($organized, $announcements);
        $html .= $this->spacer(10);
        $html .= $this->render_standard_subject_table($organized);
        $html .= $this->spacer(10);
        $html .= $this->render_remarks($organized, $staff);
        $html .= $this->spacer(10);
        $html .= $this->render_announcements($announcements);
        $html .= $this->render_footer();

        return $html;
    }

    private function render_standard_performance_summary($organized) {
        if (!$organized['course_total']) return '';
        $total    = $organized['course_total'];
        $position = $this->format_position($total['rank'] ?? null) . ' out of ' . ($total['numusers'] ?? 'N/A');

        $html  = $this->section_header('Performance Summary');
        $html .= $this->spacer(3);

        $boxes = [
            ['#003580', 'POSITION',               $position],
            ['#5d2d91', 'TOTAL MARKS OBTAINED',   $total['gradeformatted']],
            ['#0072bc', 'TOTAL MARKS OBTAINABLE', $total['grademax']],
            ['#008a76', 'PERCENTAGE',              $total['percentageformatted']],
        ];

        $html .= '<table style="width:100%; margin-bottom:3px; border-collapse:collapse;"><tr>';
        foreach ($boxes as [$bg, $label, $value]) {
            $html .= '<td bgcolor="' . $bg . '" style="width:25%; border:1px solid #000; text-align:center; color:#ffffff;">
                <table width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td style="height:40px; text-align:center; vertical-align:middle; font-weight:bold;">
                        <div style="font-size:7px; line-height:5px; margin-bottom:0;">' . $label . '</div>
                        <div style="font-size:13px;">' . $value . '</div>
                    </td>
                </tr></table>
            </td>';
        }
        $html .= '</tr></table>';

        return $html;
    }

    private function render_standard_subject_table($organized) {
        if (empty($organized['subjects'])) return '';

        $html  = $this->section_header('Subject Performance');
        $html .= $this->spacer(2);
        $html .= '<table width="100%" cellpadding="0" cellspacing="0">
        <tr bgcolor="#34495e">
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:center; width:25%;">SUBJECT</th>
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:center; width:14%;">CONTINUOUS ASSESSMENT<br>(20)</th>
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:center; width:12%;">1ST EXAM<br>(30)</th>
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:center; width:12%;">2ND EXAM<br>(50)</th>
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:center; width:12%;">TOTAL<br>(100)</th>
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:center; width:13%;">SUBJECT<br>GRADE</th>
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:center; width:12%;">SUBJECT<br>POSITION</th>
        </tr>';

        $row = 0;
        foreach ($organized['subjects'] as $subject_name => $subject_data) {
            $row++;
            $bg       = ($row % 2 == 0) ? '#f9f9f9' : '#ffffff';
            $category = $subject_data['category'];

            // For standard terms, CA/Exam are direct_mods (no third_subcat structure)
            $ca = $exam1 = $exam2 = '-';
            foreach ($subject_data['direct_mods'] as $mod) {
                $name = $mod['itemname'] ?? '';
                if (preg_match('/\s*-\s*CA\s*$/i', $name))           $ca    = $mod['gradeformatted'];
                if (preg_match('/\s*-\s*1st\s+Exam\s*$/i', $name))   $exam1 = $mod['gradeformatted'];
                if (preg_match('/\s*-\s*2nd\s+Exam\s*$/i', $name))   $exam2 = $mod['gradeformatted'];
            }

            $html .= '<tr bgcolor="' . $bg . '">
                <td style="border:1px solid #ccc; padding:5px;">'                                      . htmlspecialchars($subject_name)                     . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'                  . $ca                                                  . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'                  . $exam1                                               . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'                  . $exam2                                               . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center; font-weight:bold;">' . $category['gradeformatted']                        . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center; font-weight:bold;">' . $category['lettergradeformatted']                  . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'                  . $this->format_position($category['rank'] ?? null)    . '</td>
            </tr>';
        }
        $html .= '</table>';

        return $html;
    }

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
        $html = '<table style="width:100%; margin-top:3px; margin-bottom:3px;"><tr>';

        $html .= '<td style="width:48%; vertical-align:middle;">';
        $html .= $this->section_header('Attendance');
        $html .= $this->spacer(2);
        $html .= '<table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td bgcolor="#f5f5f5" style="padding:5px; border:1px solid #ccc; border-bottom:1px solid #000;"><strong>Days School Opened:</strong></td>
                <td style="padding:5px; text-align:center; border:1px solid #ccc; border-bottom:1px solid #000;">' . ($announcements['days_opened'] ?? 'N/A') . '</td>
            </tr>
            <tr>
                <td bgcolor="#f5f5f5" style="padding:5px; border:1px solid #ccc;"><strong>Days Present:</strong></td>
                <td style="padding:5px; text-align:center; border:1px solid #ccc;">' . ($organized['attendance']['present'] ?? 'N/A') . '</td>
            </tr>
        </table></td>';

        $html .= '<td style="width:4%;"></td>';

        $html .= '<td style="width:48%; vertical-align:middle;">';
        $html .= $this->section_header('Grade Scale');
        $html .= $this->spacer(2);
        $html .= '<table width="100%" cellpadding="0" cellspacing="0">
            <tr bgcolor="#ecf0f1">
                <th style="padding:4px; text-align:center; border:1px solid #000; font-weight:bold;">GRADE</th>
                <th style="padding:4px; text-align:center; border:1px solid #000; font-weight:bold;">RANGE</th>
                <th style="padding:4px; text-align:center; border:1px solid #000; font-weight:bold;">REMARK</th>
            </tr>';
        foreach ($this->grade_scale as $grade => $info) {
            $html .= '<tr>
                <td style="padding:3px; text-align:center; border:1px solid #ccc;">' . $grade . '</td>
                <td style="padding:3px; text-align:center; border:1px solid #ccc;">' . $info['min'] . '-' . $info['max'] . '</td>
                <td style="padding:3px; text-align:center; border:1px solid #ccc;">' . $info['remark'] . '</td>
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
        return '<div style="margin-top:8px; border-top:1px solid #333; padding-top:4px; text-align:center; font-size:7px;">
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