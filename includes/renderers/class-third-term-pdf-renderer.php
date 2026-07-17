<?php
if (!defined('ABSPATH')) exit;

trait SRL_Third_Term_PDF_Renderer {


    private function generate_third_term_pdf($pdf, $data, $is_exit_class) {
        $usergrade     = $data['grades_data']['usergrades'][0];
        $student_data  = $data['student_data'];
        $course_info   = $data['course_info'];
        $announcements = $data['announcements'];
        $staff         = $data['staff'];

        $organized = $this->organize_grade_items($usergrade['gradeitems']);

        // Header first.
        $html  = $this->get_styles();
        $html .= $this->render_page_header($usergrade, $student_data, $course_info);
        $html .= $this->spacer(3);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Direct TCPDF drawing gives reliable horizontal/vertical centering.
        if ($is_exit_class) {
            $legacy = $this->render_third_term_performance_summary(
                $organized,
                true,
                $usergrade['courseid'] ?? 0,
                $usergrade['userid'] ?? 0
            );
            $pdf->writeHTML($legacy, true, false, true, false, '');
        } else {
            $this->draw_third_term_performance_summary(
                $pdf,
                $organized,
                $usergrade['courseid'] ?? 0,
                $usergrade['userid'] ?? 0
            );
        }

        // Attendance / grade scale.
        $html_mid  = $this->spacer(3);
        $html_mid .= $this->render_attendance_and_grade_scale($organized, $announcements);
        $html_mid .= $this->spacer(3);
        $pdf->writeHTML($html_mid, true, false, true, false, '');

        if ($is_exit_class) {
            $this->draw_exit_class_subject_table($pdf, $organized);
        } else {
            $this->draw_standard_third_term_subject_table($pdf, $organized);
        }

        // Keep the end section compact so complete 3rd-term reports stay on one page.
        $html2  = $this->spacer(3);
        $html2 .= $this->render_remarks($organized, $staff);
        $html2 .= $this->spacer(2);
        $html2 .= $this->render_announcements($announcements);
        $html2 .= $this->render_footer();

        $pdf->writeHTML($html2, true, false, true, false, '');
    }


    private function draw_third_term_performance_summary($pdf, $organized, $course_id, $student_id) {
        $complete = SRL_Grade_Organizer::has_complete_term_history($organized['subjects']);
        $third    = SRL_Grade_Organizer::calc_third_term_summary($organized['subjects']);
        $total    = $organized['course_total'];

        // Section heading.
        $x = 10;
        $w = $pdf->getPageWidth() - 20;
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell($w, 8, 'PERFORMANCE SUMMARY', 0, 1, 'C', true);

        $ranking = SRL_Ranking_Repository::get_student_ranking($course_id, $student_id);
        $third_position = $ranking
            ? $this->format_position($ranking['position_num']) . ' out of ' . (int)$ranking['num_students']
            : 'N/A';

        $third_obtained_value = is_numeric(str_replace(',', '', (string)$third['obtained']))
            ? number_format((float)str_replace(',', '', (string)$third['obtained']), 2)
            : $third['obtained'];
        $third_obtainable_value = is_numeric(str_replace(',', '', (string)$third['obtainable']))
            ? number_format((float)str_replace(',', '', (string)$third['obtainable']), 0)
            : $third['obtainable'];

        $pdf->Ln(1);
        $this->draw_summary_label($pdf, '3RD TERM');
        $this->draw_summary_card_row($pdf, [
            ['POSITION', $third_position, [0, 53, 128]],
            ['TOTAL OBTAINED', $third_obtained_value, [93, 45, 145]],
            ['TOTAL OBTAINABLE', $third_obtainable_value, [0, 114, 188]],
            ['PERCENTAGE', $third['percentage'], [0, 138, 118]],
        ]);

        if ($complete && $total) {
            $pdf->Ln(2);
            $this->draw_summary_label($pdf, 'CUMULATIVE');

            $cum_position = $this->format_position($total['rank'] ?? null) . ' out of ' . ($total['numusers'] ?? 'N/A');
            $cum_obtained_value = is_numeric(str_replace(',', '', (string)($total['gradeformatted'] ?? '')))
                ? number_format((float)str_replace(',', '', (string)$total['gradeformatted']), 2)
                : ($total['gradeformatted'] ?? 'N/A');
            $cum_obtainable_value = is_numeric($total['grademax'] ?? null)
                ? number_format((float)$total['grademax'], 0)
                : ($total['grademax'] ?? 'N/A');

            $this->draw_summary_card_row($pdf, [
                ['POSITION', $cum_position, [0, 53, 128]],
                ['TOTAL OBTAINED', $cum_obtained_value, [93, 45, 145]],
                ['TOTAL OBTAINABLE', $cum_obtainable_value, [0, 114, 188]],
                ['PERCENTAGE', $total['percentageformatted'], [0, 138, 118]],
            ]);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1);
    }


    private function draw_summary_label($pdf, $label) {
        $pdf->SetFont('dejavusans', 'B', 7.5);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 4, $label, 0, 1, 'L', false);
    }


    private function draw_summary_card_row($pdf, $cards) {
        $page_w = $pdf->getPageWidth();
        $left = 10;
        $total_w = $page_w - 20;
        $card_w = $total_w / count($cards);
        $y = $pdf->GetY();
        $h = 14;

        foreach ($cards as $i => [$label, $value, $rgb]) {
            $x = $left + ($i * $card_w);

            $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x, $y, $card_w, $h, 'FD');

            // Label: centred inside upper half.
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('dejavusans', 'B', 6.2);
            $pdf->SetXY($x + 1, $y + 1.7);
            $pdf->Cell($card_w - 2, 3.5, $label, 0, 0, 'C', false);

            // Value: centred inside lower half.
            $pdf->SetFont('dejavusans', 'B', 10.5);
            $pdf->SetXY($x + 1, $y + 6.2);
            $pdf->Cell($card_w - 2, 5.5, (string)$value, 0, 0, 'C', false);
        }

        $pdf->SetY($y + $h);
    }


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
            $column_header_colors = [
                0 => [52, 73, 94],
                1 => [52, 73, 94],
                2 => [52, 73, 94],
                3 => [52, 73, 94],
                4 => [52, 73, 94],
                5 => [52, 73, 94],
                6 => [52, 73, 94],
                7 => [93, 109, 126],
                8 => [93, 109, 126],
                9 => [8, 60, 120],
                10 => [8, 60, 120],
                11 => [8, 60, 120],
            ];
            $this->draw_header_row($pdf, $cols, $lm, $y, $header_h, $header_bg, $column_header_colors);
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
        $row_h = $complete ? 5.4 : 6;
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


    private function render_third_term_performance_summary($organized, $is_exit_class = false, $course_id = 0, $student_id = 0) {
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
            ['#003580', 'POSITION', (function() use ($course_id, $student_id) {
                $ranking = SRL_Ranking_Repository::get_student_ranking($course_id, $student_id);
                if (!$ranking) return 'N/A';
                return $this->format_position($ranking['position_num']) . ' out of ' . (int)$ranking['num_students'];
            })()],
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

    private function calc_overall_cumulative_summary($subjects) {
        return SRL_Grade_Organizer::calc_overall_cumulative_summary($subjects);
    }
}
