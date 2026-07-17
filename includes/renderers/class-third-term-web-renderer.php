<?php
if (!defined('ABSPATH')) exit;

class SRL_Third_Term_Web_Renderer {
    public static function render_performance_summary($organized, $course_id, $student_id, $is_exit_class) {
        if (empty($organized['course_total'])) return;

        echo '<div class="srl-section-title">Performance Summary</div>';

        $complete = srl_has_complete_term_history($organized['subjects']);
        $third = srl_calc_third_term_summary($organized['subjects']);

        $third_obtained = is_numeric(str_replace(',', '', (string)$third['obtained']))
            ? number_format((float)str_replace(',', '', $third['obtained']), 2)
            : $third['obtained'];

        $third_obtainable = is_numeric(str_replace(',', '', (string)$third['obtainable']))
            ? number_format((float)str_replace(',', '', $third['obtainable']), 0)
            : $third['obtainable'];

        $ranking = srl_get_third_term_ranking($course_id, $student_id);
        $third_position = $ranking
            ? srl_format_position($ranking['position_num']) . ' out of ' . (int)$ranking['num_students']
            : 'N/A';

        echo '<div style="font-weight:600;color:#2c3e50;margin:10px 0 8px;">3rd Term</div>';
        echo '<div class="srl-performance-summary">';
        foreach ([
            ['Position', $third_position],
            ['Total Obtained', $third_obtained],
            ['Total Obtainable', $third_obtainable],
            ['Percentage', $third['percentage']],
        ] as [$label, $value]) {
            echo '<div class="srl-stat-card"><div class="srl-stat-label">'
                . esc_html($label)
                . '</div><div class="srl-stat-value">'
                . esc_html($value)
                . '</div></div>';
        }
        echo '</div>';

        if ($complete) {
            $total = $organized['course_total'];

            $position = srl_format_position($total['rank'] ?? null)
                . ' out of ' . ($total['numusers'] ?? 'N/A');

            $cum_obtained = is_numeric(str_replace(',', '', (string)($total['gradeformatted'] ?? '')))
                ? number_format((float)str_replace(',', '', $total['gradeformatted']), 2)
                : ($total['gradeformatted'] ?? 'N/A');

            $cum_obtainable = is_numeric($total['grademax'] ?? null)
                ? number_format((float)$total['grademax'], 0)
                : ($total['grademax'] ?? 'N/A');

            echo '<div style="font-weight:600;color:#2c3e50;margin:18px 0 8px;">Cumulative</div>';
            echo '<div class="srl-performance-summary">';

            foreach ([
                ['Position', $position],
                ['Total Obtained', $cum_obtained],
                ['Total Obtainable', $cum_obtainable],
                ['Percentage', $total['percentageformatted']],
            ] as [$label, $value]) {
                echo '<div class="srl-stat-card"><div class="srl-stat-label">'
                    . esc_html($label)
                    . '</div><div class="srl-stat-value">'
                    . esc_html($value)
                    . '</div></div>';
            }
            echo '</div>';
        }
    }


    public static function render_subject_table($subjects, $is_exit_class) {
        if ($is_exit_class) {
            self::render_exit_class_subject_table($subjects);
        } else {
            self::render_standard_third_term_subject_table($subjects);
        }
    }
    public static function render_exit_class_subject_table($subjects) {
        $complete = srl_has_complete_term_history($subjects);

        echo '<div style="overflow-x:auto;">';
        echo '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        echo '<thead>';

        if ($complete) {
            echo '<tr style="background:#34495e;color:#fff;text-align:center;">'
                . '<th rowspan="2" style="padding:6px;text-align:left;border:1px solid #ccc;">Subject</th>'
                . '<th colspan="3" style="padding:6px;border:1px solid #ccc;background:#34495e;">3RD TERM</th>'
                . '<th colspan="2" style="padding:6px;border:1px solid #ccc;background:#5d6d7e;">PRIOR TERMS</th>'
                . '<th colspan="4" style="padding:6px;border:1px solid #ccc;background:#083c78;">CUMULATIVE</th>'
                . '</tr>';

            echo '<tr style="color:#fff;text-align:center;">'
                . '<th style="padding:5px;border:1px solid #ccc;background:#34495e;">Total (100)</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#34495e;">Grade</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#34495e;">Position</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#5d6d7e;">1st Term (100)</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#5d6d7e;">2nd Term (100)</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Total (300)</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Avg</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Grade</th>'
                . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Position</th>'
                . '</tr>';
        } else {
            echo '<tr style="background:#34495e;color:#fff;text-align:center;">'
                . '<th style="padding:6px;text-align:left;border:1px solid #ccc;">Subject</th>'
                . '<th style="padding:6px;border:1px solid #ccc;">Total (100)</th>'
                . '<th style="padding:6px;border:1px solid #ccc;">Grade</th>'
                . '<th style="padding:6px;border:1px solid #ccc;">Position</th>'
                . '</tr>';
        }

        echo '</thead><tbody>';

        $row = 0;
        foreach ($subjects as $subject_name => $subject_data) {
            $row++;
            $bg = ($row % 2 === 0) ? '#f9f9f9' : '#ffffff';

            $terms  = srl_extract_term_totals($subject_data['direct_mods'] ?? []);
            $third  = $subject_data['third_subcat'] ?? null;
            $parent = $subject_data['category'] ?? [];

            // Prefer the visible 3rd-term category because it contains the subject rank.
            // Fall back to the direct "3rd Term Total" item if needed.
            $third_raw = $third['graderaw'] ?? ($terms['term3']['graderaw'] ?? null);
            $third_total = $third_raw !== null
                ? ($third['gradeformatted'] ?? $terms['term3']['formatted'] ?? '-')
                : '-';

            $third_grade = $third_raw !== null
                ? ((!empty($third['lettergradeformatted']) && $third['lettergradeformatted'] !== '-')
                    ? $third['lettergradeformatted']
                    : srl_derive_grade($third_raw))
                : '-';

            $third_pos = ($third_raw !== null && (($third['rank'] ?? 0) > 0))
                ? srl_format_position($third['rank'])
                : '-';

            $cells = [
                $subject_name,
                $third_total,
                $third_grade,
                $third_pos,
            ];

            if ($complete) {
                $cum_grade = !empty($parent['lettergradeformatted']) && $parent['lettergradeformatted'] !== '-'
                    ? $parent['lettergradeformatted']
                    : srl_derive_grade(srl_normalized_percentage($parent));

                $cum_pos = (($parent['rank'] ?? 0) > 0)
                    ? srl_format_position($parent['rank'])
                    : 'N/A';

                $cum_avg = srl_normalized_percentage($parent);
                $cells = array_merge($cells, [
                    $terms['term1']['formatted'],
                    $terms['term2']['formatted'],
                    $parent['gradeformatted'] ?? '-',
                    $cum_avg,
                    $cum_grade,
                    $cum_pos,
                ]);
            }

            echo '<tr style="background:' . $bg . ';text-align:center;">';
            foreach ($cells as $i => $value) {
                $style = 'padding:5px;border:1px solid #ddd;';
                if ($i === 0) $style .= 'text-align:left;';
                if (in_array($i, $complete ? [1, 4, 6, 7, 8] : [1], true)) $style .= 'font-weight:bold;';
                echo '<td style="' . $style . '">' . esc_html($value) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }


public static function render_standard_third_term_subject_table($subjects) {
    $complete = srl_has_complete_term_history($subjects);

    echo '<div style="overflow-x:auto;">';
    echo '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
    echo '<thead>';

    if ($complete) {
        echo '<tr style="background:#34495e;color:#fff;text-align:center;">
'
            . '<th rowspan="2" style="padding:6px;text-align:left;border:1px solid #ccc;">Subject</th>
'
            . '<th colspan="6" style="padding:6px;border:1px solid #ccc;background:#34495e;">3RD TERM</th>
'
            . '<th colspan="2" style="padding:6px;border:1px solid #ccc;background:#5d6d7e;">PRIOR TERMS</th>
'
            . '<th colspan="4" style="padding:6px;border:1px solid #ccc;background:#083c78;">CUMULATIVE</th>
'
            . '</tr>';
        echo '<tr style="background:#34495e;color:#fff;text-align:center;">
'
            . '<th style="padding:5px;border:1px solid #ccc;">CA (20)</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;">1st Exam (30)</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;">2nd Exam (50)</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;">Total (100)</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;">Grade</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;">Position</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;background:#5d6d7e;">1st Term (100)</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;background:#5d6d7e;">2nd Term (100)</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Total (300)</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Avg</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Grade</th>
'
            . '<th style="padding:5px;border:1px solid #ccc;background:#083c78;">Position</th>
'
            . '</tr>';
    } else {
        echo '<tr style="background:#34495e;color:#fff;text-align:center;">
'
            . '<th style="padding:6px;text-align:left;border:1px solid #ccc;">Subject</th>
'
            . '<th style="padding:6px;border:1px solid #ccc;">CA (20)</th>
'
            . '<th style="padding:6px;border:1px solid #ccc;">1st Exam (30)</th>
'
            . '<th style="padding:6px;border:1px solid #ccc;">2nd Exam (50)</th>
'
            . '<th style="padding:6px;border:1px solid #ccc;">Total (100)</th>
'
            . '<th style="padding:6px;border:1px solid #ccc;">Grade</th>
'
            . '<th style="padding:6px;border:1px solid #ccc;">Position</th>
'
            . '</tr>';
    }

    echo '</thead><tbody>';
    $row = 0;
    foreach ($subjects as $subject_name => $subject_data) {
        $row++;
        $bg = ($row % 2 === 0) ? '#f9f9f9' : '#ffffff';
        $terms = srl_extract_term_totals($subject_data['direct_mods'] ?? []);
        $components = srl_extract_third_term_components($subject_data['third_subcat_mods'] ?? []);
        $third = $subject_data['third_subcat'] ?? null;
        $parent = $subject_data['category'] ?? [];

        $third_total = $third['gradeformatted'] ?? '-';
        $third_grade = !empty($third['lettergradeformatted']) && $third['lettergradeformatted'] !== '-'
            ? $third['lettergradeformatted']
            : srl_derive_grade($third['graderaw'] ?? null);
        $third_pos = (($third['rank'] ?? 0) > 0) ? srl_format_position($third['rank']) : 'N/A';

        $cells = [
            $subject_name,
            $components['ca'],
            $components['exam1'],
            $components['exam2'],
            $third_total,
            $third_grade,
            $third_pos,
        ];

        if ($complete) {
            $cum_avg = srl_normalized_percentage($parent);
            $cum_grade = !empty($parent['lettergradeformatted']) && $parent['lettergradeformatted'] !== '-'
                ? $parent['lettergradeformatted']
                : srl_derive_grade($cum_avg);
            $cum_pos = (($parent['rank'] ?? 0) > 0) ? srl_format_position($parent['rank']) : 'N/A';
            $cum_avg = srl_normalized_percentage($parent);
            $cells = array_merge($cells, [
                $terms['term1']['formatted'],
                $terms['term2']['formatted'],
                $parent['gradeformatted'] ?? '-',
                $cum_avg,
                $cum_grade,
                $cum_pos,
            ]);
        }

        echo '<tr style="background:' . $bg . ';text-align:center;">';
        foreach ($cells as $i => $value) {
            $align = ($i === 0) ? 'text-align:left;' : '';
            $bold = in_array($i, [4, 6, 9, 10, 11], true) ? 'font-weight:bold;' : '';
            echo '<td style="padding:5px;border:1px solid #ddd;' . $align . $bold . '">' . esc_html($value) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}


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
}
