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

    // Form data payload (private family IDs + contact info) is exposed via data-* attributes
    // on #students-form-data, populated by the controller.
    const $formData = $('#students-form-data');
    const privateStudentIds = $formData.data('private-student-ids') ?? [];
    const familyContacts = $formData.data('private-family-contacts') ?? {};

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
            $idNumberHelp.text('Required. Unique student identifier from the school or family.');
        }
    }

    $schoolSelect.on('change', updateStudentIdRequired);
    updateStudentIdRequired();

    // Autofill Parent/Guardian fields from the selected private family's contact info.
    // On initial page load: only fill empty fields (preserve old() / existing values).
    // On user change: overwrite so the fields always match the currently selected family.
    const $parentName = $('#parent_guardian_name');
    const $parentPhone = $('#parent_guardian_phone');
    const $timezone = $('#timezone');

    function autofillParentGuardianFromFamily(overwrite) {
        const selectedSchoolId = $schoolSelect.val();
        const contact = selectedSchoolId ? familyContacts[selectedSchoolId] : null;

        const setField = ($input, value) => {
            if (overwrite || !$input.val()) {
                $input.val(value ?? '').trigger('input').trigger('change');
            }
        };

        if (contact) {
            setField($parentName, contact.name);
            setField($parentEmail, contact.email);
            setField($parentPhone, contact.phone);
            setField($timezone, contact.timezone);
            return;
        }

        // Not a private family — clear fields on user change so stale family info doesn't linger.
        if (overwrite) {
            setField($parentName, '');
            setField($parentEmail, '');
            setField($parentPhone, '');
            setField($timezone, '');
        }
    }

    $schoolSelect.on('change', () => autofillParentGuardianFromFamily(true));
    autofillParentGuardianFromFamily(false);

    // "Create and Add SSA" button sets hidden flag before form submit
    $('#create-and-add-ssa-btn').on('click', function () {
        $('#redirect_to_ssa').val('1');
    });
});
