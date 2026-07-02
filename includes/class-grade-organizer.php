<?php
/**
 * Pure grade data transformation helpers for Student Result Lookup.
 */
if (!defined('ABSPATH')) exit;

class SRL_Grade_Organizer {

    public static function organize_grade_items($gradeitems) {
        $course_total       = null;
        $attendance         = ['opened' => null, 'present' => null];
        $remarks            = ['teacher' => '', 'principal' => ''];
        $named_categories   = [];
        $unnamed_categories = [];
        $mods_by_category   = [];

        foreach ((array)$gradeitems as $item) {
            switch ($item['itemtype'] ?? '') {
                case 'course':
                    $course_total = $item;
                    break;
                case 'category':
                    if (!empty($item['itemname'])) {
                        $named_categories[$item['iteminstance']] = $item;
                    } else {
                        $unnamed_categories[$item['iteminstance']] = $item;
                    }
                    break;
                case 'mod':
                    $cid = $item['categoryid'] ?? null;
                    if ($cid !== null) $mods_by_category[$cid][] = $item;
                    break;
                case 'manual':
                    $name = $item['itemname'] ?? '';
                    if (stripos($name, 'Days Present') !== false) {
                        $attendance['present'] = $item['gradeformatted'] ?? null;
                    } elseif (stripos($name, 'Teacher') !== false && stripos($name, 'Remark') !== false) {
                        $remarks['teacher'] = $item['feedback'] ?? '';
                    } elseif (stripos($name, 'Principal') !== false && stripos($name, 'Remark') !== false) {
                        $remarks['principal'] = $item['feedback'] ?? '';
                    }
                    break;
            }
        }

        $third_term_subcats = [];
        foreach ($named_categories as $instance => $cat) {
            if (preg_match('/\s*-\s*3rd\s+Term\s*$/i', $cat['itemname'])) {
                $third_term_subcats[$instance] = $cat;
            }
        }

        $subjects = [];
        foreach ($named_categories as $instance => $cat) {
            if (isset($third_term_subcats[$instance])) continue;

            $subject_name = $cat['itemname'];
            $direct_mods  = $mods_by_category[$instance] ?? [];
            $third_subcat = null;
            $third_subcat_mods = [];

            foreach ($third_term_subcats as $sub_instance => $sub_cat) {
                if (strcasecmp($sub_cat['itemname'], $subject_name . ' - 3rd Term') === 0) {
                    $third_subcat = $sub_cat;
                    $third_subcat_mods = $mods_by_category[$sub_instance] ?? [];
                    break;
                }
            }

            if ($third_subcat === null) {
                foreach ($unnamed_categories as $sub_instance => $sub_cat) {
                    $sub_mods = $mods_by_category[$sub_instance] ?? [];
                    foreach ($sub_mods as $mod) {
                        if (stripos($mod['itemname'] ?? '', $subject_name . ' - CA') === 0 ||
                            stripos($mod['itemname'] ?? '', $subject_name . ' - 1st Exam') === 0) {
                            $third_subcat = $sub_cat;
                            $third_subcat_mods = $sub_mods;
                            break 2;
                        }
                    }
                }
            }

            $subjects[$subject_name] = [
                'category'          => $cat,
                'direct_mods'       => $direct_mods,
                'third_subcat'      => $third_subcat,
                'third_subcat_mods' => $third_subcat_mods,
            ];
        }

        return [
            'subjects'     => $subjects,
            'course_total' => $course_total,
            'attendance'   => $attendance,
            'remarks'      => $remarks,
        ];
    }

    public static function is_exit_class($class) {
        return (bool) preg_match('/^(JSS3|SS3)/i', trim((string)$class));
    }

    public static function extract_term_totals($direct_mods) {
        $result = [
            'term1' => ['formatted' => '-', 'graderaw' => null],
            'term2' => ['formatted' => '-', 'graderaw' => null],
            'term3' => ['formatted' => '-', 'graderaw' => null],
        ];
        foreach ((array)$direct_mods as $mod) {
            $name = $mod['itemname'] ?? '';
            if (preg_match('/\s*-\s*1st\s+Term\s+Total\s*$/i', $name)) {
                $result['term1'] = ['formatted' => $mod['gradeformatted'] ?? '-', 'graderaw' => $mod['graderaw'] ?? null];
            } elseif (preg_match('/\s*-\s*2nd\s+Term\s+Total\s*$/i', $name)) {
                $result['term2'] = ['formatted' => $mod['gradeformatted'] ?? '-', 'graderaw' => $mod['graderaw'] ?? null];
            } elseif (preg_match('/\s*-\s*3rd\s+Term\s+Total\s*$/i', $name)) {
                $result['term3'] = ['formatted' => $mod['gradeformatted'] ?? '-', 'graderaw' => $mod['graderaw'] ?? null];
            }
        }
        return $result;
    }

    public static function extract_third_term_components($third_subcat_mods) {
        $result = ['ca' => '-', 'exam1' => '-', 'exam2' => '-'];
        foreach ((array)$third_subcat_mods as $mod) {
            $name = $mod['itemname'] ?? '';
            if (preg_match('/\s*-\s*CA\s*$/i', $name)) $result['ca'] = $mod['gradeformatted'] ?? '-';
            if (preg_match('/\s*-\s*1st\s+Exam\s*$/i', $name)) $result['exam1'] = $mod['gradeformatted'] ?? '-';
            if (preg_match('/\s*-\s*2nd\s+Exam\s*$/i', $name)) $result['exam2'] = $mod['gradeformatted'] ?? '-';
        }
        return $result;
    }

    public static function calc_cum_avg($terms, $third_graderaw) {
        $values = [];
        foreach ([$terms['term1']['graderaw'] ?? null, $terms['term2']['graderaw'] ?? null, $third_graderaw] as $raw) {
            if ($raw !== null) $values[] = (float)$raw;
        }
        if (empty($values)) return ['avg' => null, 'terms_sat' => 0];
        return ['avg' => array_sum($values) / count($values), 'terms_sat' => count($values)];
    }

    public static function derive_grade($average) {
        static $scale = null;
        if ($scale === null) {
            $scale_file = plugin_dir_path(__FILE__) . 'grade-scale-config.php';
            $scale = file_exists($scale_file) ? include($scale_file) : [];
        }
        if (!is_numeric($average)) return '-';
        $avg = (float)$average;
        foreach ($scale as $letter => $info) {
            if ($avg >= $info['min'] && $avg <= $info['max']) return $letter;
        }
        return 'F';
    }

    public static function calc_overall_cumulative_summary($subjects) {
        $cum_totals = [];
        $cum_avgs = [];
        foreach ((array)$subjects as $subject_name => $subject_data) {
            $terms = self::extract_term_totals($subject_data['direct_mods'] ?? []);
            $third_subcat = $subject_data['third_subcat'] ?? null;
            $third_raw = $third_subcat ? ($third_subcat['graderaw'] ?? null) : ($terms['term3']['graderaw'] ?? null);
            $parent_raw = $subject_data['category']['graderaw'] ?? null;
            if ($parent_raw !== null) $cum_totals[] = (float)$parent_raw;
            $cum_avg_data = self::calc_cum_avg($terms, $third_raw);
            if ($cum_avg_data['avg'] !== null) $cum_avgs[] = $cum_avg_data['avg'];
        }
        return [
            'total' => empty($cum_totals) ? '-' : number_format(array_sum($cum_totals), 2),
            'avg'   => empty($cum_avgs) ? '-' : number_format(array_sum($cum_avgs) / count($cum_avgs), 2),
        ];
    }

    public static function format_position($number) {
        if (!is_numeric($number) || $number <= 0) return 'N/A';
        $number = (int)$number;
        $suffix = 'th';
        if (!in_array(($number % 100), [11, 12, 13], true)) {
            switch ($number % 10) {
                case 1: $suffix = 'st'; break;
                case 2: $suffix = 'nd'; break;
                case 3: $suffix = 'rd'; break;
            }
        }
        return $number . $suffix;
    }

    public static function get_component_type_third($itemname, $subject_name) {
        $suffix = $itemname;
        if (stripos($itemname, $subject_name) === 0) {
            $suffix = trim(substr($itemname, strlen($subject_name)));
            $suffix = ltrim($suffix, ' -');
        }
        if (preg_match('/^CA$/i', $suffix)) return 'CA';
        if (preg_match('/^1st\s+Exam$/i', $suffix)) return '1ST_EXAM';
        if (preg_match('/^2nd\s+Exam$/i', $suffix)) return '2ND_EXAM';
        if (preg_match('/^1st\s+Term\s+Total$/i', $suffix)) return '1ST_TERM';
        if (preg_match('/^2nd\s+Term\s+Total$/i', $suffix)) return '2ND_TERM';
        return 'UNKNOWN';
    }

    public static function get_component_type($itemname) {
        if (preg_match('/\s-\s*CA\s*$/i', $itemname) || preg_match('/\s-\s*CA\s*\(/i', $itemname)) return 'CA';
        if (stripos($itemname, '1st Exam') !== false) return '1ST_EXAM';
        if (stripos($itemname, '2nd Exam') !== false) return '2ND_EXAM';
        return 'UNKNOWN';
    }

    public static function calc_cumulative($term1, $term2, $third_total) {
        $values = [];
        foreach ([$term1, $term2, $third_total] as $v) {
            $clean = trim(str_replace(',', '.', (string)$v));
            if ($clean !== '-' && $clean !== 'N/A' && is_numeric($clean)) $values[] = (float)$clean;
        }
        if (empty($values)) return ['total' => 'N/A', 'avg' => 'N/A'];
        return ['total' => number_format(array_sum($values), 2), 'avg' => number_format(array_sum($values) / count($values), 2)];
    }

    public static function calc_overall_cumulative($subjects) {
        $all_totals = [];
        $all_avgs = [];
        foreach ((array)$subjects as $subject_name => $subject_data) {
            $components = $subject_data['components'] ?? [];
            $term1 = $term2 = '-';
            foreach ($components as $comp) {
                $type = self::get_component_type_third($comp['itemname'] ?? '', $subject_name);
                if ($type === '1ST_TERM') $term1 = $comp['gradeformatted'];
                if ($type === '2ND_TERM') $term2 = $comp['gradeformatted'];
            }
            $third_total = $subject_data['category']['gradeformatted'] ?? '-';
            $cum = self::calc_cumulative($term1, $term2, $third_total);
            if ($cum['total'] !== 'N/A') $all_totals[] = (float)str_replace(',', '.', $cum['total']);
            if ($cum['avg'] !== 'N/A') $all_avgs[] = (float)str_replace(',', '.', $cum['avg']);
        }
        return [
            'total' => empty($all_totals) ? 'N/A' : number_format(array_sum($all_totals), 2),
            'avg' => empty($all_avgs) ? 'N/A' : number_format(array_sum($all_avgs) / count($all_avgs), 2),
        ];
    }
}

// Backward-compatible wrappers: old function names still work.
function srl_organize_grade_items($gradeitems) { return SRL_Grade_Organizer::organize_grade_items($gradeitems); }
function srl_is_exit_class($class) { return SRL_Grade_Organizer::is_exit_class($class); }
function srl_extract_term_totals($direct_mods) { return SRL_Grade_Organizer::extract_term_totals($direct_mods); }
function srl_extract_third_term_components($third_subcat_mods) { return SRL_Grade_Organizer::extract_third_term_components($third_subcat_mods); }
function srl_calc_cum_avg($terms, $third_graderaw) { return SRL_Grade_Organizer::calc_cum_avg($terms, $third_graderaw); }
function srl_derive_grade($average) { return SRL_Grade_Organizer::derive_grade($average); }
function srl_calc_overall_cumulative_summary($subjects) { return SRL_Grade_Organizer::calc_overall_cumulative_summary($subjects); }
function srl_format_position($number) { return SRL_Grade_Organizer::format_position($number); }
function srl_get_component_type_third($itemname, $subject_name) { return SRL_Grade_Organizer::get_component_type_third($itemname, $subject_name); }
function srl_get_component_type($itemname) { return SRL_Grade_Organizer::get_component_type($itemname); }
function srl_calc_cumulative($term1, $term2, $third_total) { return SRL_Grade_Organizer::calc_cumulative($term1, $term2, $third_total); }
function srl_calc_overall_cumulative($subjects) { return SRL_Grade_Organizer::calc_overall_cumulative($subjects); }
