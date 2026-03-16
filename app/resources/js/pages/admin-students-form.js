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

    // Toggle Student ID required/optional based on school selection
    const privateStudentsData = $('#private-student-data');
    const privateStudentIds = privateStudentsData.length ? JSON.parse(privateStudentsData.text()) : [];
    const $schoolSelect = $('#school_id');
    const $idNumberLabel = $('#id_number_label');
    const $idNumberHelp = $('#id_number_help');

    function updateStudentIdRequired() {
        const selectedSchoolId = parseInt($schoolSelect.val(), 10);
        const isPrivate = privateStudentIds.includes(selectedSchoolId);

        if (isPrivate) {
            $idNumberLabel.text('Student ID');
            $idNumberHelp.text('Optional for private students/families. Auto-generated if left blank.');
        } else {
            $idNumberLabel.text('Student ID *');
            $idNumberHelp.text('Required. Unique student identifier from the school.');
        }
    }

    $schoolSelect.on('change', updateStudentIdRequired);
    updateStudentIdRequired();

    // "Create and Add SSA" button sets hidden flag before form submit
    $('#create-and-add-ssa-btn').on('click', function () {
        $('#redirect_to_ssa').val('1');
    });
});
