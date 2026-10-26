<?php
/*
Plugin Name: Multi-Step Course Plugin
Description: A plugin for multi-step courses with validation by admins or validators.
Version: 1.0
Author: Your Name
*/

// Define plugin path
if (! defined('ABSPATH')) exit;

define('MSC_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include files
include_once(MSC_PLUGIN_PATH . 'includes/install.php');
include_once(MSC_PLUGIN_PATH . 'includes/course-functions.php');

// Hook to run the installation function on plugin activation
register_activation_hook(__FILE__, 'msc_install');

// Register the custom post types
function msc_register_post_types()
{
    // Register Course post type
    register_post_type('msc_course', array(
        'labels' => array(
            'name' => __('Courses'),
            'singular_name' => __('Course'),
            'add_new_item' => __('Add New Course'),
            'edit_item' => __('Edit Course'),
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-welcome-learn-more',
    ));

    // Register Course Step post type
    register_post_type('msc_course_step', array(
        'labels' => array(
            'name' => __('Course Steps'),
            'singular_name' => __('Course Step'),
            'add_new_item' => __('Add New Course Step'),
            'edit_item' => __('Edit Course Step'),
        ),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-list-view',
    ));
}
add_action('init', 'msc_register_post_types');

function msc_add_course_step_metabox()
{
    add_meta_box('msc_course_step_course', __('Assign to Course'), 'msc_course_step_meta_box_callback', 'msc_course_step', 'side');
}
add_action('add_meta_boxes', 'msc_add_course_step_metabox');

function msc_course_step_meta_box_callback($post)
{
    $courses = get_posts(array('post_type' => 'msc_course', 'numberposts' => -1));
    $selected_course = get_post_meta($post->ID, '_msc_course_id', true);
?>
    <label for="msc_course_step_course"><?php _e('Select Course:'); ?></label>
    <select name="msc_course_id" id="msc_course_step_course">
        <?php foreach ($courses as $course): ?>
            <option value="<?php echo $course->ID; ?>" <?php selected($selected_course, $course->ID); ?>>
                <?php echo $course->post_title; ?>
            </option>
        <?php endforeach; ?>
    </select>
<?php
}

function msc_save_course_step_meta($post_id)
{
    if (array_key_exists('msc_course_id', $_POST)) {
        update_post_meta($post_id, '_msc_course_id', $_POST['msc_course_id']);
    }
}
add_action('save_post', 'msc_save_course_step_meta');

// Hook to add custom roles
add_action('init', 'msc_add_roles');
add_action('init', 'msc_register_post_types');
