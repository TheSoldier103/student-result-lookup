<?php
/**
 * Professional Glossy PDF Generator - FIXED VERSION v2
 * Fixed matching logic to prevent false positives (e.g., "Civic" matching "CA")
 */

if (!defined('ABSPATH')) exit;

class SRL_PDF_Generator {
    
    private $logo_path;
    private $watermark_path;
    private $grade_scale;
    
    public function __construct() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        // Define TCPDF cache path
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
        // Clean all output buffers
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
        
        $pdf->SetY(10);
        $html = $this->generate_html($data);
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = $this->generate_filename($data['grades_data']['usergrades'][0]['userfullname']);
        
        ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }

    private function section_header($title) {
        return '
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td bgcolor="#34495e"
                    style="height:22px; line-height:24px; text-align:center;
                        font-weight:bold; color:#ffffff; font-size:10px;">
                    ' . strtoupper($title) . '
                </td>
            </tr>
        </table>';
    }

    private function spacer($h = 8) {
        return '
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td style="height:' . $h . 'px; line-height:' . $h . 'px;"></td></tr>
        </table>';
    }

    /**
     * Helper function to match component type from item name
     * Uses word boundaries to prevent false matches (e.g., "Civic" shouldn't match " CA")
     */
    private function get_component_type($itemname) {
        // Check for " - CA" or " CA " or starts/ends with "CA"
        if (preg_match('/\s-\s*CA\s*$/i', $itemname) || preg_match('/\s-\s*CA\s*\(/i', $itemname)) {
            return 'CA';
        }
        // Check for "1st Exam"
        if (stripos($itemname, '1st Exam') !== false) {
            return '1ST_EXAM';
        }
        // Check for "2nd Exam"
        if (stripos($itemname, '2nd Exam') !== false) {
            return '2ND_EXAM';
        }
        return 'UNKNOWN';
    }

    
    private function generate_html($data) {
        $usergrade = $data['grades_data']['usergrades'][0];
        $student_data = $data['student_data'];
        $course_info = $data['course_info'];
        $announcements = $data['announcements'];
        $staff = $data['staff'];
        
        $organized = $this->organize_grade_items($usergrade['gradeitems']);
        $html = $this->get_styles();

        // Header - MOVED UP
        $html .= '<table style="width:100%; border-bottom:2px solid #000; padding-bottom:3px; margin-bottom:3px;"><tr>';
        
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
        
        // Student Info - 2 ROWS ONLY
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
        
        $html .= $this->spacer(6);
        
        // Performance Summary - FULL WIDTH, POSITION ON ONE LINE - IMPROVED STYLING
        if ($organized['course_total']) {
            $total = $organized['course_total'];
            $position = $this->format_position($total['rank']) . " out of " . $total['numusers'];
            
            $html .= $this->section_header('Performance Summary');
            $html .= $this->spacer(3);
            $html .= '<table style="width:100%; margin-bottom:3px; border-collapse: collapse;">
                <tr>
                    <td bgcolor="#003580" style="width:25%; border:1px solid #002147; text-align:center; color:#ffffff;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="height:40px; text-align:center; vertical-align:middle; font-weight:bold;">
                                    <div style="font-size:7px; line-height:5px; margin-bottom:0;">POSITION</div>
                                    <div style="font-size:13px;">' . $position . '</div>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td bgcolor="#5d2d91" style="width:25%; border:1px solid #3c1d5e; text-align:center; color:#ffffff;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="height:40px; text-align:center; vertical-align:middle; font-weight:bold;">
                                    <div style="font-size:7px; line-height:5px; margin-bottom:0;">TOTAL MARKS OBTAINED</div>
                                    <div style="font-size:13px;">' . $total['gradeformatted'] . '</div>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td bgcolor="#0072bc" style="width:25%; border:1px solid #005a96; text-align:center; color:#ffffff;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="height:40px; text-align:center; vertical-align:middle; font-weight:bold;">
                                    <div style="font-size:7px; line-height:5px; margin-bottom:0;">TOTAL MARKS OBTAINABLE</div>
                                    <div style="font-size:13px;">' . $total['grademax'] . '</div>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td bgcolor="#008a76" style="width:25%; border:1px solid #006b5c; text-align:center; color:#ffffff;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="height:40px; text-align:center; vertical-align:middle; font-weight:bold;">
                                    <div style="font-size:7px; line-height:5px; margin-bottom:0;">PERCENTAGE</div>
                                    <div style="font-size:13px;">' . $total['percentageformatted'] . '</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
            $html .= $this->spacer(10);
        }



        // Attendance and Grade Scale Tables
        $html .= '<table style="width:100%; margin-top:3px; margin-bottom:3px;"><tr>';
        
        // Attendance Table
        $html .= '<td style="width:48%; vertical-align:top;">';
        $html .= $this->section_header('Attendance');
        $html .= $this->spacer(2);
        $html .= '<table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td bgcolor="#f5f5f5" style="padding:5px; border:1px solid #ccc; border-bottom:1px solid #000;"><strong>Days School Opened:</strong></td>
                    <td style="padding:5px; text-align:center; border:1px solid #ccc; border-bottom:1px solid #000;">' . ($announcements['days_opened'] ?? '100') . '</td>
                </tr>
                <tr>
                    <td bgcolor="#f5f5f5" style="padding:5px; border:1px solid #ccc;"><strong>Days Present:</strong></td>
                    <td style="padding:5px; text-align:center; border:1px solid #ccc;">' . ($organized['attendance']['present'] ?? '90') . '</td>
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
        $html .= '</table>';
        $html .= '</td></tr></table>';
        
        $html .= $this->spacer(10);

        // Subject Performance
        if (!empty($organized['subjects'])) {
            $html .= $this->section_header('Subject Performance');
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
                $bg = ($row % 2 == 0) ? '#f9f9f9' : '#ffffff';
                $category = $subject_data['category'];
                $components = $subject_data['components'];
                
                $ca = $exam1 = $exam2 = '-';
                foreach ($components as $comp) {
                    $comp_type = $this->get_component_type($comp['itemname']);
                    
                    if ($comp_type === 'CA') {
                        $ca = $comp['gradeformatted'];
                    }
                    elseif ($comp_type === '1ST_EXAM') {
                        $exam1 = $comp['gradeformatted'];
                    }
                    elseif ($comp_type === '2ND_EXAM') {
                        $exam2 = $comp['gradeformatted'];
                    }
                }
                
                $html .= '<tr bgcolor="' . $bg . '">
                    <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($subject_name) . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:center;">' . $ca . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:center;">' . $exam1 . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:center;">' . $exam2 . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:center; font-weight:bold;">' . $category['gradeformatted'] . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:center; font-weight:bold;">' . $category['lettergradeformatted'] . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:center;">' . $this->format_position($category['rank']) . '</td>
                </tr>';
            }
            $html .= '</table>';
            $html .= $this->spacer(10);
        }

        // Remarks Section
        $html .= $this->section_header('Remarks');
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

        $html .= $this->spacer(10);

        // Announcements
        if ($announcements['next_term'] || $announcements['fees'] || $announcements['general']) {
            $html .= $this->section_header('Announcements');
            $html .= $this->spacer(2);
            // $html .= '<div style="font-size:7px;">';
            if ($announcements['next_term']) $html .= '<p style="margin:2px 0;"><strong>Next Term Begins:</strong> ' . $announcements['next_term'] . '</p>';
            if ($announcements['fees']) $html .= '<p style="margin:2px 0;"><strong>Fees for Next Term:</strong> ' . $announcements['fees'] . '</p>';
            if ($announcements['general']) $html .= '<p style="margin:2px 0;"><strong>Announcement:</strong> ' . strip_tags($announcements['general']) . '</p>';
            $html .= '</div>';
            $html .= $this->spacer(6);
        }

        $html .= '<div style="margin-top:8px; border-top:1px solid #333; padding-top:4px; text-align:center; font-size:7px;">
            Petra Christian Academy • Generated on ' . date('F j, Y') . '
        </div>';

        return $html;
    }

    private function get_styles() {
        return '<style>
            body { font-family: dejavusans, sans-serif; font-size: 8px; color: #000; }
            h1 { font-size: 16px; margin: 0; }
            table { border-collapse: collapse; }
        </style>';
    }

    private function organize_grade_items($gradeitems) {
        $subjects = []; 
        $course_total = null; 
        $attendance = ['present' => null]; 
        $remarks = ['teacher' => '', 'principal' => ''];
        $categories = []; 
        $components_by_category = [];

        // First pass: collect all items by type
        foreach ($gradeitems as $item) {
            if ($item['itemtype'] === 'course') { 
                $course_total = $item; 
            }
            elseif ($item['itemtype'] === 'category') { 
                $categories[$item['iteminstance']] = $item; 
            }
            elseif ($item['itemtype'] === 'mod') {
                // Ensure categoryid exists and is valid
                if (isset($item['categoryid']) && !empty($item['categoryid'])) {
                    if (!isset($components_by_category[$item['categoryid']])) {
                        $components_by_category[$item['categoryid']] = [];
                    }
                    $components_by_category[$item['categoryid']][] = $item;
                }
            } 
            elseif ($item['itemtype'] === 'manual') {
                if (stripos($item['itemname'], 'Present') !== false) {
                    $attendance['present'] = $item['gradeformatted'];
                }
                elseif (stripos($item['itemname'], 'Teacher') !== false) {
                    $remarks['teacher'] = $item['feedback'];
                }
                elseif (stripos($item['itemname'], 'Principal') !== false) {
                    $remarks['principal'] = $item['feedback'];
                }
            }
        }

        // Second pass: organize subjects with their components
        foreach ($categories as $cat_id => $category) {
            $subjects[$category['itemname']] = [
                'category' => $category,
                'components' => isset($components_by_category[$cat_id]) ? $components_by_category[$cat_id] : []
            ];
        }
        
        return [
            'subjects' => $subjects, 
            'course_total' => $course_total, 
            'attendance' => $attendance, 
            'remarks' => $remarks
        ];
    }

    private function format_position($number) {
        if (!is_numeric($number) || $number <= 0) return 'N/A';
        $number = (int)$number; $suffix = 'th';
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
}

