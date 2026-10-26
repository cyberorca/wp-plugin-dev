<?php
// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Installation Function
 */
function msc_install()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'user_course_progress';

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        course_id BIGINT(20) UNSIGNED NOT NULL,
        step_id BIGINT(20) UNSIGNED NOT NULL,
        form_data longtext NOT NULL,
        is_completed BOOLEAN DEFAULT FALSE,
        is_validated BOOLEAN DEFAULT FALSE,
        validated_by BIGINT(20) UNSIGNED NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        validated_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE,
        FOREIGN KEY (step_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE,
        FOREIGN KEY (validated_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Plugin Uninstallation Function
 * Drops the custom table and removes custom roles
 */
function msc_uninstall()
{
    global $wpdb;

    // Drop custom table
    $table_name = $wpdb->prefix . 'user_course_progress';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    // Remove custom roles
    msc_remove_roles();
}
