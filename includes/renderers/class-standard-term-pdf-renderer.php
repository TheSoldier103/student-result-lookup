<?php
if (!defined('ABSPATH')) exit;

trait SRL_Standard_Term_PDF_Renderer {


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
}
