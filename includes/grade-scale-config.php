<?php
/**
 * Grade Scale Configuration
 * Defines the grading scale for report cards
 */

if (!defined('ABSPATH')) exit;

return [
    'A' => [
        'min' => 80,
        'max' => 100,
        'remark' => 'Excellent'
    ],
    'B' => [
        'min' => 70,
        'max' => 79,
        'remark' => 'Very Good'
    ],
    'C' => [
        'min' => 60,
        'max' => 69,
        'remark' => 'Credit'
    ],
    'D' => [
        'min' => 50,
        'max' => 59,
        'remark' => 'Pass'
    ],
    'F' => [
        'min' => 0,
        'max' => 49,
        'remark' => 'Fail'
    ]
];
