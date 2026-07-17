<?php
if (!defined('ABSPATH')) exit;

class SRL_Standard_Term_Web_Renderer {

    public static function render_performance_summary($organized) {
        if (empty($organized['course_total'])) return;

        $total = $organized['course_total'];
        $position = srl_format_position($total['rank'] ?? null)
            . ' out of ' . ($total['numusers'] ?? 'N/A');

        echo '<div class="srl-section-title">Performance Summary</div>';
        echo '<div class="srl-performance-summary">';

        foreach ([
            ['Position', $position],
            ['Total Obtained', $total['gradeformatted']],
            ['Total Obtainable', $total['grademax']],
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


public static function render_subject_table($subjects) {
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
}
