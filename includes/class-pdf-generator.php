<?php
/**
 * Professional Glossy PDF Generator
 * Supports 1st/2nd term (standard layout) and 3rd term (extended layout with prior terms + cumulative)
 */

if (!defined('ABSPATH')) exit;

class SRL_PDF_Generator {
    
    private $logo_path;
    private $watermark_path;
    private $grade_scale;
    
    public function __construct() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        if (!defined('K_PATH_CACHE')) {
            $cache_dir = $plugin_dir . 'cache/';
            if (!file_exists($cache_dir)) {
                @mkdir($cache_dir, 0755, true);
            }
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
    
    public function generate_report_card($data) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
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
        
        $is_third_term = ($data['course_info']['term'] === '3rd Term');
        
        $pdf->SetY(10);

        if ($is_third_term) {
            $this->generate_third_term($pdf, $data);
        } else {
            $html = $this->generate_html($data);
            $pdf->writeHTML($html, true, false, true, false, '');
        }
        
        $filename = $this->generate_filename($data['grades_data']['usergrades'][0]['userfullname']);
        
        ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }

    // -------------------------------------------------------------------------
    // 3RD TERM PDF
    // -------------------------------------------------------------------------

    private function generate_third_term($pdf, $data) {
        $usergrade   = $data['grades_data']['usergrades'][0];
        $student_data = $data['student_data'];
        $course_info  = $data['course_info'];
        $announcements = $data['announcements'];
        $staff        = $data['staff'];

        $organized = $this->organize_grade_items($usergrade['gradeitems'], true);

        // ---- Shared header HTML (logo + title + student info) ----
        $html = $this->get_styles();
        $html .= $this->render_page_header($usergrade, $student_data, $course_info);
        $html .= $this->spacer(4);

        // ---- Performance Summary: 6 boxes in one row ----
        $html .= $this->render_third_term_performance_summary($organized);
        $html .= $this->spacer(8);

        // ---- Attendance + Grade Scale ----
        $html .= $this->render_attendance_and_grade_scale($organized, $announcements);
        $html .= $this->spacer(8);

        // Write everything so far via writeHTML
        $pdf->writeHTML($html, true, false, true, false, '');

        // ---- Subject table: mixed direct-draw (rotated headers) + HTML rows ----
        $this->render_third_term_subject_table($pdf, $organized);

        // ---- Remarks + Announcements (back to HTML) ----
        $html2  = $this->spacer(8);
        $html2 .= $this->render_remarks($organized, $staff);
        $html2 .= $this->spacer(8);
        $html2 .= $this->render_announcements($announcements);
        $html2 .= $this->render_footer();

        $pdf->writeHTML($html2, true, false, true, false, '');
    }

    /**
     * Draw the 3rd term subject table using a mix of direct TCPDF calls (rotated headers)
     * and writeHTML for data rows.
     *
     * Column layout (widths in mm, page width = 190mm):
     *  Subject         : 38
     *  CA (20)         : 14
     *  1st Exam (30)   : 14
     *  2nd Exam (50)   : 14
     *  3rd Total (100) : 16
     *  Grade (3rd)     : 13
     *  Position (3rd)  : 13
     *  1st Term (rot)  : 13
     *  2nd Term (rot)  : 13
     *  Cum. Total (rot): 13
     *  Cum. Avg (rot)  : 15
     *  Total           = 176  (leaving 14mm for left+right margin already set)
     */
    private function render_third_term_subject_table($pdf, $organized) {
        $pdf->SetFont('dejavusans', '', 7);

        $lm = $pdf->GetX(); // current left margin position
        $y  = $pdf->GetY();

        // Column definitions [label, width_mm, rotated]
        $cols = [
            ['SUBJECT',           38,  false],
            ['CA (20)',           14,  false],
            ['1ST EXAM (30)',     14,  false],
            ['2ND EXAM (50)',     14,  false],
            ['3RD TERM\nTOTAL (100)', 16, false],
            ['GRADE\n(3RD)',      13,  false],
            ['POSITION\n(3RD)',   13,  false],
            ['1ST TERM\n(100)',   13,  true],
            ['2ND TERM\n(100)',   13,  true],
            ['CUM.\nTOTAL',       13,  true],
            ['CUM.\nAVG',         15,  true],
        ];

        // How tall should the header row be?
        // Rotated columns need enough room — 18mm works well
        $header_h = 18;

        $header_bg = [52, 73, 94]; // #34495e

        // Draw header cells
        $x = $lm;
        foreach ($cols as $col) {
            [$label, $w, $rotated] = $col;

            // Draw the cell background + border
            $pdf->SetFillColor(...$header_bg);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x, $y, $w, $header_h, 'FD');

            if ($rotated) {
                // Save state, rotate 90° around the cell centre
                $cx = $x + $w / 2;
                $cy = $y + $header_h / 2;

                $pdf->StartTransform();
                $pdf->Rotate(90, $cx, $cy);

                // After rotation the "width" of the text box is header_h and "height" is w
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 6);
                $pdf->SetXY($cx - $header_h / 2, $cy - $w / 2);
                $pdf->MultiCell($header_h, $w, str_replace('\n', "\n", $label), 0, 'C', false, 0);

                $pdf->StopTransform();
            } else {
                // Normal horizontal text, vertically centred
                $lines = explode('\n', $label);
                $line_h = 3.5;
                $total_text_h = count($lines) * $line_h;
                $text_y = $y + ($header_h - $total_text_h) / 2;

                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 6);
                foreach ($lines as $line) {
                    $pdf->SetXY($x, $text_y);
                    $pdf->Cell($w, $line_h, $line, 0, 2, 'C', false);
                    $text_y += $line_h;
                }
            }

            $x += $w;
        }

        // Move cursor below the header
        $pdf->SetXY($lm, $y + $header_h);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('dejavusans', '', 7);

        // ---- Data rows ----
        $row_h = 6;
        $row_num = 0;

        foreach ($organized['subjects'] as $subject_name => $subject_data) {
            $row_num++;
            $bg = ($row_num % 2 === 0) ? [249, 249, 249] : [255, 255, 255];

            $cat        = $subject_data['category'];
            $components = $subject_data['components'];

            // Extract component values
            $ca = $exam1 = $exam2 = '-';
            $term1 = $term2 = '-';

            foreach ($components as $comp) {
                $type = $this->get_component_type_third($comp['itemname'], $subject_name);
                switch ($type) {
                    case 'CA':        $ca    = $comp['gradeformatted']; break;
                    case '1ST_EXAM':  $exam1 = $comp['gradeformatted']; break;
                    case '2ND_EXAM':  $exam2 = $comp['gradeformatted']; break;
                    case '1ST_TERM':  $term1 = $comp['gradeformatted']; break;
                    case '2ND_TERM':  $term2 = $comp['gradeformatted']; break;
                }
            }

            $third_total = $cat['gradeformatted'];
            $grade_3rd   = $cat['lettergradeformatted'];
            $pos_3rd     = $this->format_position($cat['rank'] ?? null);

            // Cumulative calculation
            $cum_data   = $this->calc_cumulative($term1, $term2, $third_total);
            $cum_total  = $cum_data['total'];
            $cum_avg    = $cum_data['avg'];

            $row_values = [
                $subject_name,
                $ca,
                $exam1,
                $exam2,
                $third_total,
                $grade_3rd,
                $pos_3rd,
                $term1,
                $term2,
                $cum_total,
                $cum_avg,
            ];

            $x = $lm;
            $pdf->SetFillColor(...$bg);
            $pdf->SetDrawColor(180, 180, 180);

            foreach ($cols as $i => [$label, $w, $rotated]) {
                $align = ($i === 0) ? 'L' : 'C';
                $bold  = in_array($i, [4, 5, 9, 10]); // 3rd total, grade, cum total, cum avg

                $pdf->SetFont('dejavusans', $bold ? 'B' : '', 7);
                $pdf->SetXY($x, $pdf->GetY());
                $pdf->Cell($w, $row_h, $row_values[$i], 1, 0, $align, true);
                $x += $w;
            }

            $pdf->Ln($row_h);
        }

        // Reset draw/fill colours
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('dejavusans', '', 8);

        // Add some space after the table
        $pdf->SetY($pdf->GetY() + 2);
    }

    /**
     * Calculate cumulative total and average across up to 3 terms.
     * Terms with value '-' or 'N/A' are excluded from the average denominator.
     */
    private function calc_cumulative($term1, $term2, $third_total) {
        $values = [];

        foreach ([$term1, $term2, $third_total] as $v) {
            $clean = trim(str_replace(',', '.', $v));
            if ($clean !== '-' && $clean !== 'N/A' && is_numeric($clean)) {
                $values[] = (float)$clean;
            }
        }

        if (empty($values)) {
            return ['total' => 'N/A', 'avg' => 'N/A'];
        }

        $total = array_sum($values);
        $avg   = $total / count($values);

        return [
            'total' => number_format($total, 2),
            'avg'   => number_format($avg, 2),
        ];
    }

    /**
     * Identify component type for 3rd term items.
     * Pattern: "<Subject> - CA", "<Subject> - 1st Exam", "<Subject> - 2nd Exam",
     *          "<Subject> - 1st Term Total", "<Subject> - 2nd Term Total"
     */
    private function get_component_type_third($itemname, $subject_name) {
        // Strip subject prefix if present
        $suffix = $itemname;
        if (stripos($itemname, $subject_name) === 0) {
            $suffix = trim(substr($itemname, strlen($subject_name)));
            $suffix = ltrim($suffix, ' -');
        }

        if (preg_match('/^CA$/i', $suffix))                        return 'CA';
        if (preg_match('/^1st\s+Exam$/i', $suffix))                return '1ST_EXAM';
        if (preg_match('/^2nd\s+Exam$/i', $suffix))                return '2ND_EXAM';
        if (preg_match('/^1st\s+Term\s+Total$/i', $suffix))        return '1ST_TERM';
        if (preg_match('/^2nd\s+Term\s+Total$/i', $suffix))        return '2ND_TERM';

        return 'UNKNOWN';
    }

    private function render_third_term_performance_summary($organized) {
        $total = $organized['course_total'];
        if (!$total) return '';

        $position   = $this->format_position($total['rank'] ?? null) . ' out of ' . ($total['numusers'] ?? 'N/A');
        $obtained   = $total['gradeformatted'];
        $obtainable = $total['grademax'];
        $pct        = $total['percentageformatted'];

        // Cumulative performance summary across all subjects
        $cum = $this->calc_overall_cumulative($organized['subjects']);

        $html  = $this->section_header('Performance Summary');
        $html .= $this->spacer(3);

        $boxes = [
            ['#003580', 'POSITION (3RD)',        $position],
            ['#5d2d91', 'TOTAL OBTAINED (3RD)',  $obtained],
            ['#0072bc', 'TOTAL OBTAINABLE (3RD)', $obtainable],
            ['#008a76', 'PERCENTAGE (3RD)',       $pct],
            ['#b5451b', 'CUMULATIVE TOTAL',       $cum['total']],
            ['#7d6608', 'CUMULATIVE AVERAGE',     $cum['avg']],
        ];

        $html .= '<table style="width:100%; margin-bottom:3px; border-collapse:collapse;"><tr>';
        foreach ($boxes as [$bg, $label, $value]) {
            $html .= '
            <td bgcolor="' . $bg . '" style="width:16.66%; border:1px solid #000; text-align:center; color:#ffffff;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="height:40px; text-align:center; vertical-align:middle; font-weight:bold;">
                            <div style="font-size:6px; line-height:5px; margin-bottom:0;">' . $label . '</div>
                            <div style="font-size:11px;">' . $value . '</div>
                        </td>
                    </tr>
                </table>
            </td>';
        }
        $html .= '</tr></table>';

        return $html;
    }

    /**
     * Sum up cumulative totals and averages across all subjects.
     */
    private function calc_overall_cumulative($subjects) {
        $all_totals = [];
        $all_avgs   = [];

        foreach ($subjects as $subject_data) {
            $cat        = $subject_data['category'];
            $components = $subject_data['components'];
            $subject_name = $subject_data['category']['itemname'] ?? '';

            $term1 = $term2 = '-';
            foreach ($components as $comp) {
                $type = $this->get_component_type_third($comp['itemname'], $subject_name);
                if ($type === '1ST_TERM') $term1 = $comp['gradeformatted'];
                if ($type === '2ND_TERM') $term2 = $comp['gradeformatted'];
            }

            $third_total = $cat['gradeformatted'];
            $cum = $this->calc_cumulative($term1, $term2, $third_total);

            if ($cum['total'] !== 'N/A') {
                $all_totals[] = (float)str_replace(',', '.', $cum['total']);
            }
            if ($cum['avg'] !== 'N/A') {
                $all_avgs[] = (float)str_replace(',', '.', $cum['avg']);
            }
        }

        return [
            'total' => empty($all_totals) ? 'N/A' : number_format(array_sum($all_totals), 2),
            'avg'   => empty($all_avgs)   ? 'N/A' : number_format(array_sum($all_avgs) / count($all_avgs), 2),
        ];
    }

    // -------------------------------------------------------------------------
    // STANDARD TERM PDF (1st / 2nd term) — unchanged logic, refactored to share helpers
    // -------------------------------------------------------------------------

    private function generate_html($data) {
        $usergrade     = $data['grades_data']['usergrades'][0];
        $student_data  = $data['student_data'];
        $course_info   = $data['course_info'];
        $announcements = $data['announcements'];
        $staff         = $data['staff'];

        $organized = $this->organize_grade_items($usergrade['gradeitems'], false);
        $html = $this->get_styles();

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
            ['#003580', 'POSITION',              $position],
            ['#5d2d91', 'TOTAL MARKS OBTAINED',  $total['gradeformatted']],
            ['#0072bc', 'TOTAL MARKS OBTAINABLE', $total['grademax']],
            ['#008a76', 'PERCENTAGE',             $total['percentageformatted']],
        ];

        $html .= '<table style="width:100%; margin-bottom:3px; border-collapse:collapse;"><tr>';
        foreach ($boxes as [$bg, $label, $value]) {
            $html .= '
            <td bgcolor="' . $bg . '" style="width:25%; border:1px solid #000; text-align:center; color:#ffffff;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="height:40px; text-align:center; vertical-align:middle; font-weight:bold;">
                            <div style="font-size:7px; line-height:5px; margin-bottom:0;">' . $label . '</div>
                            <div style="font-size:13px;">' . $value . '</div>
                        </td>
                    </tr>
                </table>
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
            <th style="border:1px solid #000; color:#fff; padding:5px; text-align:left; width:25%;">SUBJECT</th>
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
            $bg         = ($row % 2 == 0) ? '#f9f9f9' : '#ffffff';
            $category   = $subject_data['category'];
            $components = $subject_data['components'];

            $ca = $exam1 = $exam2 = '-';
            foreach ($components as $comp) {
                $type = $this->get_component_type($comp['itemname']);
                if ($type === 'CA')        $ca    = $comp['gradeformatted'];
                if ($type === '1ST_EXAM')  $exam1 = $comp['gradeformatted'];
                if ($type === '2ND_EXAM')  $exam2 = $comp['gradeformatted'];
            }

            $html .= '<tr bgcolor="' . $bg . '">
                <td style="border:1px solid #ccc; padding:5px;">'                                   . htmlspecialchars($subject_name)                    . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'               . $ca                                                . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'               . $exam1                                             . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'               . $exam2                                             . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center; font-weight:bold;">' . $category['gradeformatted']                   . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center; font-weight:bold;">' . $category['lettergradeformatted']             . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:center;">'               . $this->format_position($category['rank'] ?? null)  . '</td>
            </tr>';
        }
        $html .= '</table>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // SHARED RENDER HELPERS
    // -------------------------------------------------------------------------

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
        $html  = '<table style="width:100%; margin-top:3px; margin-bottom:3px;"><tr>';

        // Attendance
        $html .= '<td style="width:48%; vertical-align:top;">';
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
        </table>';
        $html .= '</td>';

        $html .= '<td style="width:4%;"></td>';

        // Grade Scale
        $html .= '<td style="width:48%; vertical-align:top;">';
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

        if ($announcements['next_term']) {
            $html .= '<p style="margin:2px 0;"><strong>Next Term Begins:</strong> ' . $announcements['next_term'] . '</p>';
        }
        if ($announcements['fees']) {
            $html .= '<p style="margin:2px 0;"><strong>Fees for Next Term:</strong> ' . $announcements['fees'] . '</p>';
        }
        if ($announcements['general']) {
            $html .= '<p style="margin:2px 0;"><strong>Announcement:</strong> ' . strip_tags($announcements['general']) . '</p>';
        }

        $html .= $this->spacer(6);

        return $html;
    }

    private function render_footer() {
        return '<div style="margin-top:8px; border-top:1px solid #333; padding-top:4px; text-align:center; font-size:7px;">
            Petra Christian Academy • Generated on ' . date('F j, Y') . '
        </div>';
    }

    // -------------------------------------------------------------------------
    // GRADE ITEM ORGANIZER
    // -------------------------------------------------------------------------

    /**
     * Organize grade items. In 3rd term mode, manual items with "1st Term Total"
     * or "2nd Term Total" suffixes are included as components of their subject category.
     */
    private function organize_grade_items($gradeitems, $is_third_term = false) {
        $subjects    = [];
        $course_total = null;
        $attendance  = ['present' => null];
        $remarks     = ['teacher' => '', 'principal' => ''];
        $categories  = [];
        $components_by_category = [];

        foreach ($gradeitems as $item) {
            if ($item['itemtype'] === 'course') {
                $course_total = $item;
            } elseif ($item['itemtype'] === 'category') {
                $categories[$item['iteminstance']] = $item;
            } elseif ($item['itemtype'] === 'mod') {
                if (!empty($item['categoryid'])) {
                    $components_by_category[$item['categoryid']][] = $item;
                }
            } elseif ($item['itemtype'] === 'manual') {
                $name = $item['itemname'] ?? '';

                if (stripos($name, 'Present') !== false) {
                    $attendance['present'] = $item['gradeformatted'];
                } elseif (stripos($name, 'Teacher') !== false && stripos($name, 'Remark') !== false) {
                    $remarks['teacher'] = $item['feedback'];
                } elseif (stripos($name, 'Principal') !== false && stripos($name, 'Remark') !== false) {
                    $remarks['principal'] = $item['feedback'];
                } elseif ($is_third_term && (
                    preg_match('/\s*-\s*1st\s+Term\s+Total\s*$/i', $name) ||
                    preg_match('/\s*-\s*2nd\s+Term\s+Total\s*$/i', $name)
                )) {
                    // Attach to the parent category via categoryid
                    if (!empty($item['categoryid'])) {
                        $components_by_category[$item['categoryid']][] = $item;
                    }
                }
            }
        }

        foreach ($categories as $cat_id => $category) {
            $subjects[$category['itemname']] = [
                'category'   => $category,
                'components' => $components_by_category[$cat_id] ?? [],
            ];
        }

        return [
            'subjects'     => $subjects,
            'course_total' => $course_total,
            'attendance'   => $attendance,
            'remarks'      => $remarks,
        ];
    }

    // -------------------------------------------------------------------------
    // SMALL HELPERS (unchanged)
    // -------------------------------------------------------------------------

    private function section_header($title) {
        return '<table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td bgcolor="#34495e" style="height:22px; line-height:24px; text-align:center; font-weight:bold; color:#ffffff; font-size:10px;">
                    ' . strtoupper($title) . '
                </td>
            </tr>
        </table>';
    }

    private function spacer($h = 8) {
        return '<table width="100%" cellpadding="0" cellspacing="0">
            <tr><td style="height:' . $h . 'px; line-height:' . $h . 'px;"></td></tr>
        </table>';
    }

    /**
     * Match component type for standard (1st/2nd) term items.
     * Uses suffix matching to avoid false positives (e.g. "Civic" != "CA").
     */
    private function get_component_type($itemname) {
        if (preg_match('/\s-\s*CA\s*$/i', $itemname) || preg_match('/\s-\s*CA\s*\(/i', $itemname)) return 'CA';
        if (stripos($itemname, '1st Exam') !== false) return '1ST_EXAM';
        if (stripos($itemname, '2nd Exam') !== false) return '2ND_EXAM';
        return 'UNKNOWN';
    }

    private function format_position($number) {
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