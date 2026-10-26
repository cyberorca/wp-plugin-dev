<?php

// Register custom post types for Courses and Course Steps
function msc_register_post_types()
{
    // Register course post type
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

    // Register course step post type
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

// Meta box for assigning steps to a course
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

// Shortcode to display course progress
function msc_display_course($atts)
{
    global $wpdb;
    $atts = shortcode_atts(array('course_id' => 0), $atts, 'display_course');
    $course_id = intval($atts['course_id']);

    // Fetch the course and steps
    $course = get_post($course_id);
    $steps = new WP_Query(array(
        'post_type' => 'msc_course_step',
        'meta_key' => 'course_id',
        'meta_value' => $course_id,
        'posts_per_page' => -1
    ));

    if ($course && $steps->have_posts()) {
        echo '<h2>' . esc_html($course->post_title) . '</h2>';
        echo '<div>' . esc_html($course->post_content) . '</div>';

        echo '<ol>';
        while ($steps->have_posts()) : $steps->the_post();
            echo '<li>' . get_the_title() . '</li>';
            // Add a button to complete the step
            echo '<form method="POST">
                <input type="hidden" name="course_id" value="' . esc_attr($course_id) . '"/>
                <input type="hidden" name="step_id" value="' . get_the_ID() . '"/>
                <button type="submit" name="complete_step">Complete Step</button>
            </form>';
        endwhile;
        echo '</ol>';

        wp_reset_postdata();
    } else {
        echo 'Course not found or no steps available.';
    }
}
add_shortcode('display_course', 'msc_display_course');

// Handle course step completion
function msc_handle_complete_step()
{
    if (isset($_POST['complete_step'])) {
        global $wpdb;
        $user_id = get_current_user_id();
        $course_id = intval($_POST['course_id']);
        $step_id = intval($_POST['step_id']);

        // Insert user progress into wp_user_course_progress
        $wpdb->insert(
            "{$wpdb->prefix}user_course_progress",
            array(
                'user_id'      => $user_id,
                'course_id'    => $course_id,
                'step_id'      => $step_id,
                'is_completed' => 1,
                'completed_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%d', '%s')
        );
    }
}
add_action('init', 'msc_handle_complete_step');

// Validator page in admin
function msc_register_admin_page()
{
    add_menu_page(
        'Course Validation',
        'Course Validation',
        'manage_options',
        'course-validation',
        'msc_admin_validation_page'
    );
}

function msc_admin_validation_page()
{
    global $wpdb;

    // Get all user progress entries
    $progress_data = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}user_course_progress
        WHERE is_completed = 1 AND is_validated = 0
    ");

    echo '<h2>Validate Completed Steps</h2>';
    echo '<table>';
    echo '<tr><th>User ID</th><th>Course ID</th><th>Step ID</th><th>Action</th></tr>';

    foreach ($progress_data as $progress) {
        echo '<tr>';
        echo '<td>' . esc_html($progress->user_id) . '</td>';
        echo '<td>' . esc_html($progress->course_id) . '</td>';
        echo '<td>' . esc_html($progress->step_id) . '</td>';
        echo '<td>
                <form method="POST">
                    <input type="hidden" name="progress_id" value="' . esc_attr($progress->id) . '"/>
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
add_action('admin_menu', 'msc_register_admin_page');
