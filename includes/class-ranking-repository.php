<?php
if (!defined('ABSPATH')) exit;

class SRL_Ranking_Repository {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'srl_term_rankings';
    }

    public static function install() {
        global $wpdb;

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            student_name VARCHAR(191) NOT NULL DEFAULT '',
            term VARCHAR(50) NOT NULL DEFAULT '3rd Term',
            session VARCHAR(50) NOT NULL DEFAULT '',
            total_obtained DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_obtainable DECIMAL(12,2) NOT NULL DEFAULT 0,
            percentage DECIMAL(7,2) NOT NULL DEFAULT 0,
            position_num INT UNSIGNED NOT NULL DEFAULT 0,
            num_students INT UNSIGNED NOT NULL DEFAULT 0,
            calculated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY course_student (course_id, student_id),
            KEY course_id (course_id),
            KEY session_term (session, term)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public static function upsert_many($rows) {
        global $wpdb;
        $table = self::table_name();

        foreach ((array)$rows as $row) {
            $wpdb->replace(
                $table,
                [
                    'course_id' => (int)$row['course_id'],
                    'student_id' => (int)$row['student_id'],
                    'student_name' => (string)$row['student_name'],
                    'term' => (string)($row['term'] ?? '3rd Term'),
                    'session' => (string)($row['session'] ?? ''),
                    'total_obtained' => (float)$row['total_obtained'],
                    'total_obtainable' => (float)$row['total_obtainable'],
                    'percentage' => (float)$row['percentage'],
                    'position_num' => (int)$row['position_num'],
                    'num_students' => (int)$row['num_students'],
                    'calculated_at' => (string)$row['calculated_at'],
                ],
                ['%d','%d','%s','%s','%s','%f','%f','%f','%d','%d','%s']
            );
        }
    }

    public static function delete_course($course_id) {
        global $wpdb;
        $wpdb->delete(self::table_name(), ['course_id' => (int)$course_id], ['%d']);
    }

    public static function get_student_ranking($course_id, $student_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table_name() . " WHERE course_id = %d AND student_id = %d LIMIT 1",
                $course_id,
                $student_id
            ),
            ARRAY_A
        );
    }

    public static function get_course_rankings($course_id) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::table_name() . " WHERE course_id = %d ORDER BY position_num ASC, percentage DESC, student_name ASC",
                $course_id
            ),
            ARRAY_A
        );
    }

    public static function get_course_meta($course_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT calculated_at, COUNT(*) AS student_count
                 FROM " . self::table_name() . "
                 WHERE course_id = %d
                 GROUP BY calculated_at
                 ORDER BY calculated_at DESC
                 LIMIT 1",
                $course_id
            ),
            ARRAY_A
        );
    }
}

function srl_get_third_term_ranking($course_id, $student_id) {
    return SRL_Ranking_Repository::get_student_ranking($course_id, $student_id);
}
