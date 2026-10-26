<?php
function msc_install()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Courses Table
    $table_courses = $wpdb->prefix . 'course';
    $sql_courses = "CREATE TABLE $table_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_name VARCHAR(255) NOT NULL,
        description TEXT,
        total_steps INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Course Steps Table
    $table_steps = $wpdb->prefix . 'course_steps';
    $sql_steps = "CREATE TABLE $table_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        step_number INT NOT NULL,
        step_name VARCHAR(255) NOT NULL,
        step_content TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES $table_courses(id) ON DELETE CASCADE
    ) $charset_collate;";

    // User Progress Table
    $table_progress = $wpdb->prefix . 'user_course_progress';
    $sql_progress = "CREATE TABLE $table_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        course_id INT NOT NULL,
        step_id INT NOT NULL,
        is_completed BOOLEAN DEFAULT FALSE,
        is_validated BOOLEAN DEFAULT FALSE,
        validated_by BIGINT(20) UNSIGNED,
        completed_at TIMESTAMP NULL,
        validated_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES $table_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (step_id) REFERENCES $table_steps(id) ON DELETE CASCADE,
        FOREIGN KEY (validated_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_courses);
    dbDelta($sql_steps);
    dbDelta($sql_progress);
}
