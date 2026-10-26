<?php
function msc_add_roles()
{
    add_role(
        'validator',
        __('Validator'),
        array(
            'read' => true,
            'validate_courses' => true,
        )
    );
}

function msc_remove_roles()
{
    remove_role('validator');
}

// Register custom post type for courses (optional, if you want to manage courses as custom post types)
function msc_register_post_types()
{
    register_post_type('msc_course', array(
        'labels' => array(
            'name' => 'Courses',
            'singular_name' => 'Course',
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'menu_icon' => 'dashicons-welcome-learn-more',
    ));
}


function msc_update_user_progress($user_id, $course_id, $step_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'user_course_progress';

    // Update or insert progress
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND course_id = %d AND step_id = %d",
            $user_id,
            $course_id,
            $step_id
        )
    );

    if ($exists) {
        $wpdb->update(
            $table,
            array(
                'is_completed' => 1,
                'completed_at' => current_time('mysql')
            ),
            array('id' => $exists)
        );
    } else {
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'step_id' => $step_id,
                'is_completed' => 1,
                'completed_at' => current_time('mysql')
            )
        );
    }
}

function msc_validate_user_progress($user_id, $course_id, $step_id, $validator_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'user_course_progress';

    $wpdb->update(
        $table,
        array(
            'is_validated' => 1,
            'validated_by' => $validator_id,
            'validated_at' => current_time('mysql')
        ),
        array(
            'user_id' => $user_id,
            'course_id' => $course_id,
            'step_id' => $step_id
        )
    );
}

register_uninstall_hook(__FILE__, 'msc_uninstall');

function msc_uninstall()
{
    global $wpdb;

    // Drop custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}course");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}course_steps");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}user_course_progress");

    // Remove roles
    msc_remove_roles();
}

// Shortcode to display course progress for users
function msc_display_user_course($atts)
{
    global $wpdb;
    $user_id = get_current_user_id();
    $course_id = $atts['course_id']; // Pass course_id through shortcode [display_course course_id="X"]

    if (!$user_id) {
        return 'You need to be logged in to view this course.';
    }

    // Fetch steps for the course
    $steps = get_posts(array(
        'post_type' => 'msc_course_step',
        'meta_key' => '_msc_course_id',
        'meta_value' => $course_id,
        'orderby' => 'date',
        'order' => 'ASC'
    ));

    $output = '<div class="msc-course">';
    foreach ($steps as $step) {
        // Check if the user has completed the step
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT is_completed FROM {$wpdb->prefix}user_course_progress WHERE user_id = %d AND course_id = %d AND step_id = %d",
            $user_id,
            $course_id,
            $step->ID
        ));

        $status = $completed ? 'Completed' : 'Not completed';
        $output .= '<div class="msc-course-step">';
        $output .= '<h3>' . $step->post_title . '</h3>';
        $output .= '<p>' . $step->post_content . '</p>';
        $output .= '<p>Status: ' . $status . '</p>';
        if (!$completed) {
            $output .= '<form method="POST"><input type="hidden" name="step_id" value="' . $step->ID . '"/><button type="submit" name="complete_step">Mark as Completed</button></form>';
        }
        $output .= '</div>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode('display_course', 'msc_display_user_course');

function msc_handle_complete_step()
{
    if (isset($_POST['complete_step'])) {
        global $wpdb;
        $user_id = get_current_user_id();
        $step_id = intval($_POST['step_id']);
        $course_id = get_post_meta($step_id, '_msc_course_id', true);

        // Mark step as completed
        $wpdb->insert($wpdb->prefix . 'user_course_progress', array(
            'user_id' => $user_id,
            'course_id' => $course_id,
            'step_id' => $step_id,
            'is_completed' => 1,
            'completed_at' => current_time('mysql')
        ));
    }
}
add_action('init', 'msc_handle_complete_step');

// Admin page to show user progress
function msc_register_admin_page()
{
    add_menu_page('Course Validation', 'Course Validation', 'validate_courses', 'msc-course-validation', 'msc_course_validation_page');
}
add_action('admin_menu', 'msc_register_admin_page');

function msc_course_validation_page()
{
    global $wpdb;

    // Display user progress and allow validation
    $progress_data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}user_course_progress WHERE is_completed = 1 AND is_validated = 0");

    echo '<h2>Course Validation</h2>';
    echo '<table><tr><th>User ID</th><th>Course ID</th><th>Step ID</th><th>Action</th></tr>';
    foreach ($progress_data as $progress) {
        echo '<tr>';
        echo '<td>' . $progress->user_id . '</td>';
        echo '<td>' . $progress->course_id . '</td>';
        echo '<td>' . $progress->step_id . '</td>';
        echo '<td>
                <form method="POST">
                    <input type="hidden" name="progress_id" value="' . $progress->id . '"/>
                    <button type="submit" name="validate_step">Validate</button>
                </form>
              </td>';
        echo '</tr>';
    }
    echo '</table>';
}

// Handle validation
function msc_handle_validation()
{
    if (isset($_POST['validate_step'])) {
        global $wpdb;
        $progress_id = intval($_POST['progress_id']);
        $validator_id = get_current_user_id();

        $wpdb->update(
            $wpdb->prefix . 'user_course_progress',
            array(
                'is_validated' => 1,
                'validated_by' => $validator_id,
                'validated_at' => current_time('mysql')
            ),
            array('id' => $progress_id)
        );
    }
}
add_action('admin_init', 'msc_handle_validation');
