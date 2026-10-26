function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

jQuery(function($) {
    //catch forminator submitted form
    $(document).on('forminator:form:submit:success', function(e, fid) {
        console.log(e.target.parentElement.parentElement.id, e.target.id, e);
        var parentID = e.target.parentElement.parentElement.id,
            completeForm = $(`#msc-complete-step-form-${parentID}`),
            course_id = $(`#msc-complete-step-form-${parentID} input[name="course_id"]`).val(),
            step_id = $(`#msc-complete-step-form-${parentID} input[name="step_id"]`).val(),
            btnComplete = $(`#btn-${parentID}`),
            formId = e.target.querySelector("input[name='form_id']").value;
        console.log("parent id : course_id : step_id => ", parentID, course_id, step_id, formId, formId);

        $.ajax({
            url: msc_ajax_object.ajax_url, // The localized AJAX URL from wp_localize_script
            type: 'POST',
            data: {
                action: 'msc_custom_button_action', // The AJAX action registered in PHP
                form_id: formId, // Send the form ID for any additional logic
                course_id: course_id,
                step_id: step_id
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    // You can perform additional actions here if needed
                } else {
                    // alert(response.message);
                }
            },
            error: function(error) {
                console.log('AJAX Error:', error);
            }
        });

        console.log("btnComplete", btnComplete.length);
        !btnComplete.length ? completeForm.append(`<button type="submit" name="msc_complete_step" id="btn-${parentID}" class="msc_complete_step button button-primary">Mark as Completed</button>`) : '';

    });
});