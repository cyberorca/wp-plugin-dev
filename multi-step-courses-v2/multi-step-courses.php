<?php
/*
Plugin Name: Multi-Step Courses
Description: A plugin for managing multi-step courses with admin validation.
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (! defined('ABSPATH')) exit;

define('MSC_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Add custom roles for validators
function msc_add_roles()
{
    // Check if the 'validator' role already exists
    if (!get_role('validator')) {
        // Add the 'validator' role
        add_role(
            'validator',
            __('Validator'),
            array(
                'read' => true,  // Validator can read content
                'validate_courses' => true, // Custom capability for validating courses
            )
        );
    }
}

// Include dependencies
include_once(MSC_PLUGIN_PATH . 'includes/install.php');
include_once(MSC_PLUGIN_PATH . 'includes/course-functions.php');

// Register hooks
register_activation_hook(__FILE__, 'msc_install');
add_action('init', 'msc_add_roles');
add_action('init', 'msc_register_post_types');
add_action('admin_menu', 'msc_register_admin_page');
add_action('admin_init', 'msc_handle_validation');
add_action('init', 'msc_handle_complete_step');
