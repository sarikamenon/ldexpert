import $ from 'jquery';

$(function () {
    const $notificationEmail = $('#email');
    const $parentEmail = $('#parent_guardian_email');
    const $scheduleEmail = $('#schedule_email');
    const $parentCheckbox = $('#parent_email_same_as_notification');
    const $scheduleCheckbox = $('#schedule_email_same_as_notification');

    function syncEmail($checkbox, $targetInput) {
        if ($checkbox.is(':checked')) {
            $targetInput.val($notificationEmail.val());
        }
    }

    $parentCheckbox.on('change', function () {
        syncEmail($parentCheckbox, $parentEmail);
    });

    $scheduleCheckbox.on('change', function () {
        syncEmail($scheduleCheckbox, $scheduleEmail);
    });

    $notificationEmail.on('input', function () {
        if ($parentCheckbox.is(':checked')) {
            $parentEmail.val($notificationEmail.val());
        }
        if ($scheduleCheckbox.is(':checked')) {
            $scheduleEmail.val($notificationEmail.val());
        }
    });

    // "Create and Add SSA" button sets hidden flag before form submit
    $('#create-and-add-ssa-btn').on('click', function () {
        $('#redirect_to_ssa').val('1');
    });
});
