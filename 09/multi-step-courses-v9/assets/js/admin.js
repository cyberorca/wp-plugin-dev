jQuery(document).ready(function($) {
    // Ensure DataTables is initialized after DOM is ready
    if ($.fn.DataTable) {
        new DataTable('table.display');


        $("#validate-users").DataTable({
            dom: '<"dt-buttons"Bf><"clear">lirtp',
            paging: true,
            autoWidth: true,
            buttons: [
                "colvis",
                "copyHtml5",
                "csvHtml5",
                "excelHtml5",
                "pdfHtml5",
                "print"
            ],
            initComplete: function(settings, json) {
                $(".dt-buttons .btn-group").append(
                    '<a id="cv" class="btn btn-primary" href="#">CARD VIEW</a>'
                );
                $("#cv").on("click", function() {
                    if ($("#validate-users").hasClass("card")) {
                        $(".colHeader").remove();
                    } else {
                        var labels = [];
                        $("#validate-users thead th").each(function() {
                            labels.push($(this).text());
                        });
                        $("#validate-users tbody tr").each(function() {
                            $(this)
                                .find("td")
                                .each(function(column) {
                                    $("<span class='colHeader'>" + labels[column] + ":</span>").prependTo(
                                        $(this)
                                    );
                                });
                        });
                    }
                    $("#validate-users").toggleClass("card");
                });
            }
        });
        // $("table.display").DataTable({
        //     "dom": '<"dt-buttons"Bfli>rtp',
        //     "paging": false,
        //     "autoWidth": true,
        //     "fixedHeader": true,
        //     "buttons": [
        //         'colvis',
        //         'copyHtml5',
        //         'csvHtml5',
        //         'excelHtml5',
        //         'pdfHtml5',
        //         'print'
        //     ]
        // });
        // var TblUnvalidateCourse = new DataTable('#unvalidate-course');
        // var TblValidateCourse = new DataTable('#validate-course');
        // var TblUncompleteCourse = new DataTable('#uncomplete-course');
        // document.title = "Simple DataTable";
        // Create search inputs in footer
        // $("#unvalidate-course tfoot th").each(function() {
        //     var title = $(this).text();
        //     $(this).html('<input type="text" placeholder="Search ' + title + '" />');
        // });
        // $('#unvalidate-course').DataTable({
        //     "dom": '<"dt-buttons"Bf><"clear">lirtp',
        //     "paging": true,
        //     "autoWidth": true,
        //     "buttons": [
        //         'colvis',
        //         'copyHtml5',
        //         'csvHtml5',
        //         'excelHtml5',
        //         'pdfHtml5',
        //         'print'
        //     ],
        //     initComplete: function(settings, json) {
        //         var footer = $("#unvalidate-course tfoot tr");
        //         $("#unvalidate-course thead").append(footer);
        //     }
        // });
        // // Apply the search
        // $("#unvalidate-course thead").on("keyup", "input", function() {
        //     table.column($(this).parent().index())
        //         .search(this.value)
        //         .draw();
        // });
        // $('#submissionsTable').DataTable({
        //     // "processing": true,
        //     // "serverSide": true,
        //     "ajax": {
        //         "url": msc_ajax_object.msc_get_submission_for_validation_action,
        //         "type": "POST",
        //         "dataSrc": "data"
        //     },
        //     "columns": [{
        //             "data": "id"
        //         },
        //         {
        //             "data": "user_login"
        //         },
        //         {
        //             "data": "course_title"
        //         },
        //         {
        //             "data": "step_title"
        //         },
        //         {
        //             "data": "form_data"
        //         },
        //         {
        //             "data": "action"
        //         }
        //     ]
        // });
    } else {
        console.error('DataTables not loaded!');
    }
});