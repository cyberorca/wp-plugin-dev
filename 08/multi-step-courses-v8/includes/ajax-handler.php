<?php
// AJAX handler for logged-in users
function msc_handle_custom_button_ajax()
{
    // Perform any action you want here. For example, mark a course step as complete.
    // Get the current user ID
    $user_id = get_current_user_id();

    if ($user_id) {
        // Get and sanitize input
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;


        if (!$course_id || !$step_id || !$user_id) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_course_progress';

        $entry = Forminator_API::get_entries($form_id);
        $form_data = json_encode($entry[0]->meta_data, JSON_UNESCAPED_UNICODE);

        // Check if progress already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND course_id = %d AND step_id = %d",
            $user_id,
            $course_id,
            $step_id
        ));

        if (!$existing) {
            // Insert new record
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'step_id' => $step_id,
                    'form_data' => $form_data,
                    'is_completed' => 0,
                    'completed_at' => current_time('mysql'),
                ),
                array(
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%d',
                    '%s'
                )
            );
        }
        $response = array(
            'status'  => 'success',
            'message' => 'Button clicked successfully! course_id : ' . $_POST['course_id'] . " | step_id : " . $_POST['step_id'] . " | form_id : " . $form_id . " | " . $form_data,
        );
        wp_redirect(add_query_arg('step_completed', 'true', wp_get_referer()));
    } else {
        $response = array(
            'status'  => 'error',
            'message' => 'User is not logged in.',
        );
    }

    // Send the response in JSON format
    wp_send_json($response);
}

// Register the AJAX action for logged-in users
add_action('wp_ajax_msc_custom_button_action', 'msc_handle_custom_button_ajax');

// Register the AJAX action for non-logged-in users (if required)
add_action('wp_ajax_nopriv_msc_custom_button_action', 'msc_handle_custom_button_ajax');
