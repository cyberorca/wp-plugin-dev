<?php
/*
Plugin Name: Multi-Step Courses
Description: A plugin for managing multi-step courses with admin validation.
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin path
define('MSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MSC_PLUGIN_URL', plugin_dir_URL(__FILE__));

// Include installation and function files
require_once MSC_PLUGIN_PATH . 'includes/install.php';
require_once MSC_PLUGIN_PATH . 'includes/course-functions.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'msc_install');
register_deactivation_hook(__FILE__, 'msc_uninstall');

// Initialize plugin functions
add_action('init', 'msc_register_post_types');
add_action('init', 'msc_handle_complete_step');
add_action('init', 'msc_add_roles');
add_action('admin_menu', 'msc_register_admin_page');
add_action('admin_init', 'msc_handle_validation');

// Enqueue Scripts and Styles
add_action('wp_enqueue_scripts', 'msc_enqueue_assets');
add_action('admin_enqueue_scripts', 'msc_enqueue_admin_assets');
