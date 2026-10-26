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
function msc_get_validate_step()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_course_progress';
    // Fetch only unvalidated submissions
    $submissions = $wpdb->get_row("
        SELECT COUNT(ID) as total FROM $table_name p WHERE p.is_validated = 0;
    ");
    return $submissions;
}

function msc_display_course($atts)
{
    if (! is_user_logged_in()) {
        return __('You need to be logged in to view this course.', 'multi-step-courses');
    }

    $atts = shortcode_atts(array(
        'course_id' => 0
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
        echo '<ol class="cd-breadcrumb triangle custom-icons msc-course-left">';
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
                $content .= '<form method="POST" class="msc-complete-step-form" id="msc-complete-step-form-' . $stepPage . '">';
                $content .= '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
                $content .= '<input type="hidden" name="step_id" value="' . esc_attr($step_id) . '">' . wp_nonce_field('msc_complete_step', 'msc_complete_step_nonce');
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
    $validate_step = msc_get_validate_step();
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
    add_menu_page(
        __('Rekap Peserta', 'multi-step-courses'),
        __('Rekap Peserta', 'multi-step-courses'),
        'validate_courses',
        'msc-daftar-peserta',
        'msc_admin_daftar_peserta',
        'dashicons-groups',
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
        u.user_login,
        p.is_validated,
	    p.is_completed
    FROM
        {$wpdb->prefix}postmeta pm
        LEFT JOIN {$wpdb->prefix}user_course_progress p ON p.step_id = pm.post_id
        LEFT JOIN {$wpdb->prefix}posts c ON pm.post_id = c.ID
        LEFT JOIN {$wpdb->prefix}posts s ON pm.meta_value = s.ID
        LEFT JOIN {$wpdb->prefix}users u ON p.user_id = u.ID
    WHERE pm.meta_key LIKE '%msc%'
    ");

    return $uncompleted_submissions;
}

function msc_rekap_data($isValidated = 1)
{
    global $wpdb;

    $validatedUser = $wpdb->get_results("SELECT 
        u.ID AS user_id, 
        u.user_login, 
        u.user_email, 
        c.post_title AS course_title, 
        MAX(s.post_title) AS last_step_title, 
        COUNT(CASE WHEN p.is_validated = 1 THEN 1 END) AS total_validated_steps,
        GROUP_CONCAT(CASE WHEN p.is_validated = 1 THEN s.post_title END ORDER BY s.ID SEPARATOR ', ') AS validated_steps,
        COUNT(CASE WHEN p.is_validated = 0 THEN 1 END) AS total_completed_not_validated,
        GROUP_CONCAT(CASE WHEN p.is_validated = 0 THEN s.post_title END ORDER BY s.ID SEPARATOR ', ') AS completed_steps,
        MAX(p.completed_at) AS last_completed_at
    FROM {$wpdb->prefix}user_course_progress p
    INNER JOIN {$wpdb->prefix}users u ON p.user_id = u.ID
    INNER JOIN {$wpdb->prefix}posts c ON p.course_id = c.ID -- Assuming course is stored in {$wpdb->prefix}posts
    INNER JOIN {$wpdb->prefix}posts s ON p.step_id = s.ID -- Assuming step is stored in wp_posts
    WHERE p.is_completed = 1
    GROUP BY u.ID, c.post_title
    ORDER BY last_completed_at DESC");
    return $validatedUser;
}

function msc_admin_daftar_peserta()
{

    if (! current_user_can('validate_courses')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'multi-step-courses'));
    }
    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>' . __('Data Peserta Course', 'multi-step-courses') . '</h1>';

    $validatedUsers = msc_rekap_data(1);

?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Rekap Peserta
                    </div>
                    <div class="panel-body">
                        <table id="validate-users" class="table table-striped table-bordered" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>Peserta</th>
                                    <th>Course</th>
                                    <th>Step Terakhir</th>
                                    <th>Step Completed</th>
                                    <th>Total Completed</th>
                                    <th>Step Tervalidasi</th>
                                    <th>Total Valid</th>
                                    <th>Tgl. Validasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($validatedUsers) : ?>
                                    <?php foreach ($validatedUsers as $validatedUser) : ?>
                                        <tr>
                                            <td><?php echo $validatedUser->user_login; ?></td>
                                            <td><?php echo esc_html($validatedUser->course_title); ?></td>
                                            <td><?php echo esc_html($validatedUser->last_step_title); ?></td>
                                            <td><?php echo esc_html($validatedUser->completed_steps); ?></td>
                                            <td><?php echo esc_html($validatedUser->total_completed_not_validated); ?></td>
                                            <td><?php echo esc_html($validatedUser->validated_steps); ?></td>
                                            <td><?php echo esc_html($validatedUser->total_validated_steps); ?></td>
                                            <td><?php echo esc_html($validatedUser->last_completed_at); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php // else : 
                                    ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No submissions awaiting validation.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
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
    }
    $submissions = msc_get_submissions_for_validation();

?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Validation Status
                    </div>
                    <div class="panel-body">
                        <h1>Course Belum Tervalidasi</h1>
                        <table id="unvalidate-course" class="table table-striped table-bordered" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>Peserta</th>
                                    <th>Course Step</th>
                                    <th>Submission Data</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($submissions) : ?>
                                    <?php foreach ($submissions as $submission) : ?>
                                        <tr>
                                            <td><?php echo $submission->user_login; ?></td>
                                            <td><?php echo esc_html($submission->step_title); ?></td>
                                            <td>
                                                <?php
                                                $decodedDataSubmission = json_decode($submission->form_data);
                                                echo msc_convert_complex_to_html($decodedDataSubmission);
                                                ?>
                                            </td>
                                            <td>
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
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No submissions awaiting validation.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <h1>Course Tervalidasi</h1>
                        <table id="validate-course" class="table table-striped table-bordered" cellspacing="0" width="100%">
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
                                <?php
                                $validated_submissions = msc_get_validated_submissions();
                                if ($validated_submissions) :
                                ?>
                                    <?php foreach ($validated_submissions as $validated_submission) : ?>

                                        <tr>
                                            <td><?php echo $validated_submission->user_login; ?></td>
                                            <td><?php echo esc_html($validated_submission->step_title); ?></td>
                                            <td>
                                                <?php
                                                $decodedData = json_decode($validated_submission->form_data);
                                                echo msc_convert_complex_to_html($decodedData);
                                                ?>
                                            </td>
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
                        </table>
                        <h1>Course Tidak Lengkap</h1>
                        <table id="uncomplete-course" class="table table-striped table-bordered" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>Course Title</th>
                                    <th>Step Title</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $un_completed_submissions = msc_get_un_completed_submissions();
                                if ($un_completed_submissions) :
                                ?>
                                    <?php foreach ($un_completed_submissions as $un_completed_submission) : ?>
                                        <?php if ($un_completed_submission->step_id == null || $un_completed_submission->is_completed == 0) : ?>

                                            <tr>
                                                <td><?php echo esc_html($un_completed_submission->course_title); ?></td>
                                                <td><?php echo esc_html($un_completed_submission->step_title); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5">No un-completed submissions found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}

function msc_convert_complex_to_html($object)
{
    $html = '<table border="1" cellpadding="5" cellspacing="0">'; // Start table
    $html .= '<tr><th>Field</th><th>Value</th></tr>'; // Table headers

    foreach ($object as $key => $field) {
        $label = ucwords(str_replace('-', ' ', $key)); // Human-readable label

        // Handling different types of fields
        if (strpos($key, 'checkbox') !== false || strpos($key, 'radio') !== false) {
            // Checkbox or radio: handle as a comma-separated list of values
            $html .= '<tr><td>' . htmlspecialchars($label) . '</td>';
            $html .= '<td>' . htmlspecialchars($field->value) . '</td></tr>'; // Already a string with comma-separated values
        } elseif (strpos($key, 'upload') !== false) {
            // File upload: Display file name and link
            $html .= '<tr><td>' . htmlspecialchars($label) . '</td>';
            if ($field->value->file->success) {
                $file_url = htmlspecialchars($field->value->file->file_url);
                $file_name = htmlspecialchars($field->value->file->file_name);
                $html .= '<td><a href="' . $file_url . '" target="_blank">' . $file_name . '</a></td></tr>';
            } else {
                $html .= '<td>Upload failed or not available</td></tr>';
            }
        } elseif (strpos($key, 'address') !== false) {
            // File upload: Display file name and link
            $addresEncode = msc_raw_json_to_text(json_encode($field->value));
            $html .= '<tr><td>' . htmlspecialchars($label) . '</td>';
            $html .= '<td>' . $addresEncode . '</td></tr>';
        } else {
            // Default: Render as plain text
            $html .= '<tr><td>' . htmlspecialchars($label) . '</td>';
            $html .= '<td>' . htmlspecialchars($field->value) . '</td></tr>';
        }
    }

    $html .= '</table>'; // End table
    return $html;
}

function msc_raw_json_to_text($json)
{
    // Decode the JSON into an associative array
    $data = json_decode($json, true);

    // Check if JSON decoding was successful
    if (json_last_error() === JSON_ERROR_NONE) {
        // Recursive function to traverse the array and convert to a formatted text string
        function jsonToText($data, $indent = 0)
        {
            $text = '';
            $prefix = str_repeat(" ", $indent); // Indentation for nested structures

            foreach ($data as $key => $value) {
                // Check if the value is an array (to handle nested structures)
                if (is_array($value)) {
                    $text .= $prefix . ucfirst($key) . ":\n";
                    $text .= jsonToText($value, $indent + 4); // Indent further for nested items
                } else {
                    // Add the key-value pair to the text, with proper formatting
                    $text .= $prefix . ucfirst($key) . ": " . $value . "\n";
                }
            }

            return $text;
        }

        // Convert the decoded JSON to text
        $text = jsonToText($data);

        // Output the plain text (or save to a file, send via email, etc.)
        return nl2br($text); // For HTML display, use nl2br to convert newlines to <br> tags
    } else {
        // Handle JSON errors (e.g., invalid JSON string)
        return "Invalid JSON format";
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
 * Handle Filtering Form
 */
function msc_handle_filtering()
{
    if (isset($_POST['msc_filtering'])) {
        // Verify nonce
        if (! isset($_POST['msc_filtering_nonce']) || ! wp_verify_nonce($_POST['msc_filtering_nonce'], 'msc_filtering')) {
            wp_die(__('Invalid request.', 'multi-step-courses'));
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_course_progress';
    }
}
add_action('admin_init', 'msc_handle_filtering');


/**
 * Enqueue Front-End Scripts and Styles
 */
function msc_enqueue_assets()
{
    wp_register_script('msc-script', MSC_PLUGIN_URL . 'assets/js/custom.js', array(), false, array(
        'in_footer' => true,
        'strategy'  => 'async',
    ));
    wp_enqueue_style('msc-style', MSC_PLUGIN_URL . 'assets/css/style.css');
    wp_enqueue_script('msc-script-modernizr', MSC_PLUGIN_URL . 'assets/js/modernizr.js', null, null, true);
    wp_enqueue_script(
        'msc-script-custom',
        MSC_PLUGIN_URL . 'assets/js/custom.js',
        array(),
        '1.0.1',
        array(
            'in_footer' => true,
            'strategy'  => 'async',
        )
    );
    wp_localize_script('msc-script', 'msc_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));  // Localize AJAX URL
    wp_enqueue_script('msc-script');
}

/**
 * Enqueue Admin Scripts and Styles
 */
function msc_enqueue_admin_assets($hook)
{
    if (str_contains($hook, 'msc-') === true) {
        wp_enqueue_style('msc-bootstrap-admin-style', MSC_PLUGIN_URL . 'assets/css/datatables/bootstrap.min.css');
        wp_enqueue_style('msc-btn-bootstrap-admin-style', MSC_PLUGIN_URL . 'assets/css/datatables/buttons.bootstrap.min.css');
        wp_enqueue_style('msc-dttables-bootstrap-admin-style', MSC_PLUGIN_URL . 'assets/css/datatables/dataTables.bootstrap.min.css');
        wp_enqueue_style('msc-dttables-bootstrap-admin-style', MSC_PLUGIN_URL . 'assets/css/datatables/admin.css');

        wp_enqueue_script('msc-jquery-datatables-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/jquery.dataTables.min.js', array('jquery'), null, true);
        wp_enqueue_script('msc-datatables-bs-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/dataTables.bootstrap.min.js', array('jquery', 'msc-jquery-datatables-admin-script'), null, true);
        wp_enqueue_script('msc-datatables-btn-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/dataTables.buttons.min.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script'), null, true);
        wp_enqueue_script('msc-btn-bs-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/buttons.bootstrap.min.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script'), null, true);
        wp_enqueue_script('msc-btn-colvis-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/buttons.colVis.min.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script', 'msc-btn-bs-admin-script'), null, true);
        wp_enqueue_script('msc-btn-html5-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/buttons.html5.min.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script', 'msc-btn-bs-admin-script', 'msc-btn-colvis-admin-script'), null, true);
        wp_enqueue_script('msc-btn-print-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/buttons.print.min.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script', 'msc-btn-bs-admin-script', 'msc-btn-colvis-admin-script', 'msc-btn-html5-admin-script'), null, true);
        wp_enqueue_script('msc-jzip-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/jszip.min.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script', 'msc-btn-bs-admin-script', 'msc-btn-colvis-admin-script', 'msc-btn-html5-admin-script', 'msc-btn-print-admin-script'), null, true);
        wp_enqueue_script('msc-pdfmake-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/pdfmake.min.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script', 'msc-btn-bs-admin-script', 'msc-btn-colvis-admin-script', 'msc-btn-html5-admin-script', 'msc-btn-print-admin-script', 'msc-jzip-admin-script'), null, true);
        wp_enqueue_script('msc-vfs-font-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/vfs_fonts.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script', 'msc-btn-bs-admin-script', 'msc-btn-colvis-admin-script', 'msc-btn-html5-admin-script', 'msc-btn-print-admin-script', 'msc-jzip-admin-script', 'msc-pdfmake-admin-script'), null, true);
        wp_enqueue_script('msc-admin-script', MSC_PLUGIN_URL . 'assets/js/datatables/admin.js', array('jquery', 'msc-jquery-datatables-admin-script', 'msc-datatables-bs-admin-script', 'msc-datatables-btn-admin-script', 'msc-btn-bs-admin-script', 'msc-btn-colvis-admin-script', 'msc-btn-html5-admin-script', 'msc-btn-print-admin-script', 'msc-jzip-admin-script', 'msc-pdfmake-admin-script', 'msc-vfs-font-admin-script'), '1.0.1', true);
    }

    // Localize the script for AJAX URL
    wp_localize_script('msc-admin-script', 'msc_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}


add_action('forminator_form_submit_response', 'save_fielder_details', 10, 2);
add_action('forminator_form_ajax_submit_response', 'save_fielder_details', 10, 2);
function save_fielder_details($response, $form_id)
{
    $success = isset($response['success']) ? $response['success'] : 0;
    if ($success == 1) {
        $form_id = $response['form_id'];
        $entry = Forminator_API::get_entries($form_id);
        error_log(json_encode($entry[0]->meta_data));
    }
    return $response;
}
