jQuery(document).ready(function($) {
    // Ensure DataTables is initialized after DOM is ready
    if ($.fn.DataTable) {
        $("#validate-users, #unvalidate-course, #validate-course, #uncomplete-course").DataTable({
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
                // $(".dt-buttons .btn-group").append(
                //     '<a id="cv" class="btn btn-primary" href="#">CARD VIEW</a>'
                // );
                // $("#cv").on("click", function() {
                //     if ($("#validate-users").hasClass("card")) {
                //         $(".colHeader").remove();
                //     } else {
                //         var labels = [];
                //         $("#validate-users thead th").each(function() {
                //             labels.push($(this).text());
                //         });
                //         $("#validate-users tbody tr").each(function() {
                //             $(this)
                //                 .find("td")
                //                 .each(function(column) {
                //                     $("<span class='colHeader'>" + labels[column] + ":</span>").prependTo(
                //                         $(this)
                //                     );
                //                 });
                //         });
                //     }
                //     $("#validate-users").toggleClass("card");
                // });
            }
        });
    } else {
        console.error('DataTables not loaded!');
    }
});