<?php
// Prevent direct access

use function PHPUnit\Framework\isNull;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register Custom Post Types: Courses and Course Steps
 */
function msc_register_post_types()
{
    // Register Course Post Type
    register_post_type('msc_course', array(
        'labels' => array(
            'name' => __('Courses', 'multi-step-courses'),
            'singular_name' => __('Course', 'multi-step-courses'),
            'add_new_item' => __('Add New Course', 'multi-step-courses'),
            'edit_item' => __('Edit Course', 'multi-step-courses'),
            'view_item' => __('View Course', 'multi-step-courses'),
            'all_items' => __('All Courses', 'multi-step-courses'),
            'search_items' => __('Search Courses', 'multi-step-courses'),
            'not_found' => __('No courses found', 'multi-step-courses'),
            'not_found_in_trash' => __('No courses found in Trash', 'multi-step-courses'),
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-welcome-learn-more',
    ));

    // Register Course Step Post Type
    register_post_type('msc_course_step', array(
        'labels' => array(
            'name' => __('Course Steps', 'multi-step-courses'),
            'singular_name' => __('Course Step', 'multi-step-courses'),
            'add_new_item' => __('Add New Course Step', 'multi-step-courses'),
            'edit_item' => __('Edit Course Step', 'multi-step-courses'),
            'view_item' => __('View Course Step', 'multi-step-courses'),
            'all_items' => __('All Course Steps', 'multi-step-courses'),
            'search_items' => __('Search Course Steps', 'multi-step-courses'),
            'not_found' => __('No course steps found', 'multi-step-courses'),
            'not_found_in_trash' => __('No course steps found in Trash', 'multi-step-courses'),
        ),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-list-view',
    ));
}
add_action('init', 'msc_register_post_types');

/**
 * Add Meta Boxes for Course Steps to Assign to a Course
 */
function msc_add_course_step_metabox()
{
    add_meta_box(
        'msc_course_step_course',
        __('Assign to Course', 'multi-step-courses'),
        'msc_course_step_meta_box_callback',
        'msc_course_step',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'msc_add_course_step_metabox');

/**
 * Callback Function for Course Assignment Meta Box
 */
function msc_course_step_meta_box_callback($post)
{
    // Retrieve existing value
    $assigned_course = get_post_meta($post->ID, '_msc_course_id', true);

    // Fetch all courses
    $courses = get_posts(array(
        'post_type' => 'msc_course',
        'numberposts' => -1,
        'post_status' => 'publish',
    ));

?>
    <label for="msc_course_id"><?php _e('Select Course:', 'multi-step-courses'); ?></label>
    <select name="msc_course_id" id="msc_course_id" style="width:100%;">
        <option value=""><?php _e('None', 'multi-step-courses'); ?></option>
        <?php foreach ($courses as $course): ?>
            <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($assigned_course, $course->ID); ?>>
                <?php echo esc_html($course->post_title); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

/**
 * Save Meta Box Data
 */
function msc_save_course_step_meta($post_id)
{
    // Check if our nonce is set.
    if (! isset($_POST['msc_course_id'])) {
        return;
    }

    // Verify that the user has permission to edit the post.
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sanitize user input.
    $course_id = intval($_POST['msc_course_id']);

    // Update the meta field in the database.
    update_post_meta($post_id, '_msc_course_id', $course_id);
}
add_action('save_post', 'msc_save_course_step_meta');

/**
 * Add Custom Roles: Validator
 */
function msc_add_roles()
{
    add_role(
        'validator',
        __('Validator', 'multi-step-courses'),
        array(
            'read' => true,
            'validate_courses' => true,
        )
    );
}
add_action('init', 'msc_add_roles');

/**
 * Remove Custom Roles upon Uninstallation
 */
function msc_remove_roles()
{
    remove_role('validator');
}

/**
 * Shortcode to Display Course and Its Steps
 */
function msc_display_course($atts)
{
    if (! is_user_logged_in()) {
        return __('You need to be logged in to view this course.', 'multi-step-courses');
    }

    $atts = shortcode_atts(array(
        'course_id' => 0,
    ), $atts, 'display_course');

    $course_id = intval($atts['course_id']);

    if (!$course_id) {
        return __('Invalid Course ID.', 'multi-step-courses');
    }

    $course = get_post($course_id);

    if (!$course || $course->post_type !== 'msc_course') {
        return __('Course not found.', 'multi-step-courses');
    }

    // Fetch steps associated with this course
    $steps = new WP_Query(array(
        'post_type' => 'msc_course_step',
        'meta_key' => '_msc_course_id',
        'meta_value' => $course_id,
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ));

    ob_start(); // Start output buffering

    echo '<div class="msc-course">';
    echo '<h2>' . esc_html($course->post_title) . '</h2>';
    echo '<div class="msc-course-content">' . apply_filters('the_content', $course->post_content) . '</div>';
    $isCurrent = false;
    $content = "";
    $count = 1;
    $onlyOne = false;
    if ($steps->have_posts()) {
        echo '<ol class="cd-breadcrumb triangle custom-icons">';
        while ($steps->have_posts()) {
            $steps->the_post();
            $step_id = get_the_ID();
            $step_title = get_the_title();
            $step_content = get_the_content();
            $stepPage = "step-" . $step_id;
            // Check user progress
            global $wpdb;
            $user_id = get_current_user_id();
            $table_name = $wpdb->prefix . 'user_course_progress';
            $progress = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d AND course_id = %d AND step_id = %d",
                $user_id,
                $course_id,
                $step_id
            ));

            $sum = $wpdb->get_row("SELECT COUNT(id) AS 'total' FROM wp_user_course_progress;");

            if (is_null($progress)) {
                $is_completed = false;
                $is_validated = false;
                $isCurrent = $count == 1  ? true : false;
            } else {
                $is_completed = $progress->is_completed == 1;
                $is_validated = $progress->is_validated == 1;
            }

            if ($is_completed && !$is_validated && !$onlyOne) {
                $isCurrent =  true;
                $onlyOne = true;
            }
            if (!$is_completed && !$is_validated && !$onlyOne) {
                $isCurrent =  true;
                $onlyOne = true;
            }

            echo '<li ' . ($isCurrent ? 'class="current"' : '') . '>';
            echo $isCurrent ? '<a class="tablinks" onclick="openTab(event, \'' . $stepPage . '\')">' . esc_html($step_title) . '</a>' : '<em>' . esc_html($step_title) . '</em>';
            echo '</li>';

            $content .= '<div id="' . $stepPage . '" class="tabcontent ' . ($isCurrent ? "active" : '') . ' ">';
            $content .= '<div class="msc-step-content">' . apply_filters('the_content', $step_content) . '</div>';
            $content .= '<div class="msc-step-status">';
            $content .= '<strong>' . __('Status:', 'multi-step-courses') . '</strong> ';
            if ($is_completed && $is_validated) {
                $content .= '<span class="msc-status-validated">' . __('Validated', 'multi-step-courses') . '</span>';
            } elseif ($is_completed) {
                $content .= '<span class="msc-status-completed">' . __('Completed (Awaiting Validation)', 'multi-step-courses') . '</span>';
            } else {
                $content .= '<span class="msc-status-pending">' . __('Not Completed', 'multi-step-courses') . '</span>';
            }
            $content .= '</div>';

            // If not completed, show a form to mark as completed
            if (!$is_completed) {
                $content .= '<form method="POST" class="msc-complete-step-form">';
                $content .= '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
                $content .= '<input type="hidden" name="step_id" value="' . esc_attr($step_id) . '">' . wp_nonce_field('msc_complete_step', 'msc_complete_step_nonce');
                $content .= '<button type="submit" name="msc_complete_step" class="button button-primary">' . __('Mark as Completed', 'multi-step-courses') . '</button>';
                $content .= '</form>';
            }
            $content .= '</div>';

            $count++;
        }
        echo '</ol>';
        echo $content;
    } else {
        echo __('No steps found for this course.', 'multi-step-courses');
    }

    echo '</div>'; // .msc-course

    wp_reset_postdata();

    return ob_get_clean(); // Return the buffered content
}
add_shortcode('display_course', 'msc_display_course');

/**
 * Handle Step Completion Form Submission
 */
function msc_handle_complete_step()
{
    if (isset($_POST['msc_complete_step'])) {
        // Verify nonce
        if (! isset($_POST['msc_complete_step_nonce']) || ! wp_verify_nonce($_POST['msc_complete_step_nonce'], 'msc_complete_step')) {
            return;
        }

        // Get and sanitize input
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;
        $user_id = get_current_user_id();

        if (!$course_id || !$step_id || !$user_id) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_course_progress';

        // Check if progress already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND course_id = %d AND step_id = %d",
            $user_id,
            $course_id,
            $step_id
        ));

        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                array(
                    'is_completed' => 1,
                    'completed_at' => current_time('mysql'),
                ),
                array(
                    'id' => $existing->id
                ),
                array(
                    '%d',
                    '%s'
                ),
                array(
                    '%d'
                )
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'step_id' => $step_id,
                    'is_completed' => 1,
                    'completed_at' => current_time('mysql'),
                ),
                array(
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%s'
                )
            );
        }

        // Redirect to avoid form resubmission
        wp_redirect(add_query_arg('step_completed', 'true', wp_get_referer()));
        exit;
    }
}
add_action('init', 'msc_handle_complete_step');

/**
 * Register Admin Page for Course Validation
 */
function msc_register_admin_page()
{
    add_menu_page(
        __('Course Validation', 'multi-step-courses'),
        __('Course Validation', 'multi-step-courses'),
        'validate_courses',
        'msc-course-validation',
        'msc_admin_validation_page',
        'dashicons-yes',
        6
    );
}
add_action('admin_menu', 'msc_register_admin_page');
/**
 * Callback Function for Admin Validation Page
 */
function msc_get_submissions_for_validation()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_course_progress';

    // Fetch only unvalidated submissions
    // $submissions = $wpdb->get_results("SELECT * FROM $table_name WHERE is_validated = 0");
    $submissions = $wpdb->get_results("
        SELECT p.*, u.user_login AS user_login, c.post_title AS course_title, s.post_title AS step_title
        FROM $table_name p
        INNER JOIN {$wpdb->prefix}users u ON p.user_id = u.ID
        INNER JOIN {$wpdb->prefix}posts c ON p.course_id = c.ID
        INNER JOIN {$wpdb->prefix}posts s ON p.step_id = s.ID
        WHERE p.is_completed = 1 AND p.is_validated = 0
    ");
    return $submissions;
}

// Fetch all validated submissions
function msc_get_validated_submissions()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_course_progress';

    // Fetch validated submissions
    // $validated_submissions = $wpdb->get_results("SELECT * FROM $table_name WHERE is_validated = 1");
    $validated_submissions = $wpdb->get_results("
        SELECT p.*, u.user_login AS user_login, c.post_title AS course_title, s.post_title AS step_title
        FROM $table_name p
        INNER JOIN {$wpdb->prefix}users u ON p.user_id = u.ID
        INNER JOIN {$wpdb->prefix}posts c ON p.course_id = c.ID
        INNER JOIN {$wpdb->prefix}posts s ON p.step_id = s.ID
        WHERE p.is_completed = 1 AND p.is_validated = 1
    ");
    return $validated_submissions;
}

// Fetch all un-completed submissions
function msc_get_un_completed_submissions()
{
    global $wpdb;

    // Fetch un-completed submissions
    $uncompleted_submissions = $wpdb->get_results("
    SELECT
        pm.post_id,
        p.step_id,
        c.post_title AS step_title,
        s.post_title AS course_title,
        u.user_login
    FROM
        wp_postmeta pm
        LEFT JOIN wp_user_course_progress p ON p.step_id = pm.post_id
        LEFT JOIN wp_posts c ON pm.post_id = c.ID
        LEFT JOIN wp_posts s ON pm.meta_value = s.ID
        LEFT JOIN wp_users u ON p.user_id = u.ID
    WHERE pm.meta_key LIKE '%msc%'
    ");

    return $uncompleted_submissions;
}

function msc_admin_validation_page()
{
    if (! current_user_can('validate_courses')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'multi-step-courses'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'user_course_progress';

    echo '<div class="wrap">';
    echo '<h1>' . __('Course Validation', 'multi-step-courses') . '</h1>';

    if (isset($_GET['validation_success']) && $_GET['validation_success'] == 'true') {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . __('Step validated successfully.', 'multi-step-courses') . '</p>';
        echo '</div>';
        $submissions = msc_get_submissions_for_validation();
    ?>
        <style>
            .tb-info {
                background: antiquewhite;
                text-align: center;
            }
        </style>
        <div class="wrap">
            <h1>Course Step Submissions</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Course Step</th>
                        <th>Submission Data</th>
                        <th>Action</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions) : ?>
                        <?php foreach ($submissions as $submission) : ?>
                            <tr>
                                <td><?php echo $submission->user_login; ?></td>
                                <td><?php echo esc_html($submission->step_title); ?></td>
                                <td><?php echo esc_html($submission->form_data); ?></td>
                                <td colspan="1">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="msc_validate_step_nonce" value="<?php echo wp_create_nonce('msc_validate_step'); ?>">
                                        <input type="hidden" name="progress_id" value="<?php echo esc_attr($submission->id); ?>">
                                        <button type="submit" name="msc_validate_step" class="button button-primary"><?php echo __('Validate', 'multi-step-courses') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php // else : 
                        ?>
                    <?php endif; ?>
                    <tr>
                        <td colspan="5">No submissions awaiting validation.</td>
                    </tr>
                    <?php
                    $validated_submissions = msc_get_validated_submissions();
                    if ($validated_submissions) :
                    ?>
                        <tr>
                            <td colspan="5" class="tb-info"><strong>Validated Submissions:</strong></td>
                        </tr>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Course Step</th>
                                <th>Submission Data</th>
                                <th>Validated At</th>
                                <th>Validated By</th>
                            </tr>
                        </thead>
                <tbody>
                    <?php foreach ($validated_submissions as $validated_submission) : ?>
                        <tr>
                            <td><?php echo $validated_submission->user_login; ?></td>
                            <td><?php echo esc_html($validated_submission->step_title); ?></td>
                            <td><?php echo esc_html($validated_submission->form_data); ?></td>
                            <td><?php echo esc_html($validated_submission->completed_at); ?></td>
                            <td><?php echo WP_User::get_data_by("ID", $validated_submission->validated_by)->user_login; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No validated submissions found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>

                <?php
                $un_completed_submissions = msc_get_un_completed_submissions();
                if ($un_completed_submissions) :
                ?>
                    <tr>
                        <td colspan="5" class="tb-info"><strong>Un-completed Submissions:</strong></td>
                    </tr>
                    <thead>
                        <tr>
                            <th colspan="2">Course Title</th>
                            <th colspan="3">Step Title</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($un_completed_submissions as $un_completed_submission) : ?>
                            <?php if ($un_completed_submission->step_id == null) : ?>

                                <tr>
                                    <td colspan="2"><?php echo esc_html($un_completed_submission->course_title); ?></td>
                                    <td colspan="3"><?php echo esc_html($un_completed_submission->step_title); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">No un-completed submissions found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                    </tbody>
            </table>
        </div>
<?php
    }
}

/**
 * Handle Validation Form Submission
 */
function msc_handle_validation()
{
    if (isset($_POST['msc_validate_step'])) {
        // Verify nonce
        if (! isset($_POST['msc_validate_step_nonce']) || ! wp_verify_nonce($_POST['msc_validate_step_nonce'], 'msc_validate_step')) {
            wp_die(__('Invalid request.', 'multi-step-courses'));
        }

        // Get and sanitize input
        $progress_id = isset($_POST['progress_id']) ? intval($_POST['progress_id']) : 0;
        $validator_id = get_current_user_id();

        if (!$progress_id || !$validator_id) {
            wp_die(__('Invalid data.', 'multi-step-courses'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_course_progress';

        // Update the record to mark as validated
        $updated = $wpdb->update(
            $table_name,
            array(
                'is_validated' => 1,
                'validated_by' => $validator_id,
                'validated_at' => current_time('mysql'),
            ),
            array(
                'id' => $progress_id
            ),
            array(
                '%d',
                '%d',
                '%s'
            ),
            array(
                '%d'
            )
        );

        if ($updated !== false) {
            // Redirect to avoid resubmission and show success message
            wp_redirect(admin_url('admin.php?page=msc-course-validation&validation_success=true'));
            exit;
        } else {
            wp_die(__('Failed to validate step.', 'multi-step-courses'));
        }
    }
}
add_action('admin_init', 'msc_handle_validation');

/**
 * Enqueue Front-End Scripts and Styles
 */
function msc_enqueue_assets()
{

    wp_enqueue_style('msc-style', MSC_PLUGIN_URL . 'assets/css/style.css');
    wp_enqueue_script('msc-script-modernizr', MSC_PLUGIN_URL . 'assets/js/modernizr.js', null, null, true);
    wp_enqueue_script('msc-script-custom', MSC_PLUGIN_URL . 'assets/js/custom.js', null, null, true);
}

/**
 * Enqueue Admin Scripts and Styles
 */
function msc_enqueue_admin_assets($hook)
{
    if (strpos($hook, 'msc-course-validation') === false) {
        return;
    }

    wp_enqueue_style('msc-admin-style', plugins_url('assets/css/style.css', __FILE__));
    wp_enqueue_script('msc-admin-script', plugins_url('assets/js/script.js', __FILE__), array('jquery'), null, true);
}


add_action('forminator_form_submit_response', 'save_fielder_details', 10, 2);
add_action('forminator_form_ajax_submit_response', 'save_fielder_details', 10, 2);
function save_fielder_details($response, $form_id)
{
    $success = isset($response['success']) ? $response['success'] : 0;
    if ($success == 1) {
        $form_id = $response['form_id'];
        // error_log(print_r($response['form_id'], true));
        $entry = Forminator_API::get_entries($form_id);
        error_log(print_r($entry, true));
    }


    return $response;
}
