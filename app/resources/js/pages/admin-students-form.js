import $ from 'jquery';
import Swal from 'sweetalert2';

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
    const schoolTimezones = $formData.data('school-timezones') ?? {};

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

        // Timezone follows the selected school/family for every school, not just private
        // families — schoolTimezones maps school id => timezone.
        const schoolTimezone = selectedSchoolId ? schoolTimezones[selectedSchoolId] : null;
        setField($timezone, schoolTimezone);

        if (contact) {
            setField($parentName, contact.name);
            setField($parentEmail, contact.email);
            setField($parentPhone, contact.phone);
            return;
        }

        // Not a private family — clear parent contact fields on user change so stale
        // family info doesn't linger. Timezone is handled above for all schools.
        if (overwrite) {
            setField($parentName, '');
            setField($parentEmail, '');
            setField($parentPhone, '');
        }
    }

    $schoolSelect.on('change', () => autofillParentGuardianFromFamily(true));
    autofillParentGuardianFromFamily(false);

    // "Create and Add SSA" button sets hidden flag before form submit
    $('#create-and-add-ssa-btn').on('click', function () {
        $('#redirect_to_ssa').val('1');
    });

    // Duplicate-student warning (vanilla JS). The server runs a name-gate check before
    // creating/updating; if it finds possible duplicates and the admin has not
    // acknowledged them, it redirects back with the matches flashed to the
    // data-duplicate-matches attribute. On load we surface them in a confirm dialog.
    const formDataEl = document.getElementById('students-form-data');
    const studentForm = formDataEl?.closest('form') ?? document.querySelector('form');
    const ackInput = document.getElementById('duplicate_acknowledged');

    const escapeHtml = (value) =>
        String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[char]);

    const renderMatch = (match) => {
        const row = (label, value) =>
            value
                ? `<div class="text-sm text-foreground/70"><span class="font-medium text-foreground/80">${label}:</span> ${escapeHtml(value)}</div>`
                : '';

        const details = [
            row('Username', match.username),
            row('School/Family', match.school_name),
            row('Email', match.email),
            row('DOB', match.date_of_birth),
            row('Grade', match.grade_level),
        ]
            .filter(Boolean)
            .join('');

        return `
            <li class="mb-3">
                <a href="${escapeHtml(match.show_url)}" target="_blank" rel="noopener"
                   class="font-medium text-primary underline">${escapeHtml(match.name)}</a>
                ${details}
            </li>`;
    };

    let duplicateMatches = [];
    try {
        duplicateMatches = JSON.parse(formDataEl?.dataset.duplicateMatches ?? '[]');
    } catch {
        duplicateMatches = [];
    }

    if (Array.isArray(duplicateMatches) && duplicateMatches.length > 0 && studentForm && ackInput) {
        const label = duplicateMatches.length === 1 ? 'student' : 'students';

        Swal.fire({
            title: 'Possible duplicate student',
            html: `
                <p class="mb-3 text-left">We already have ${duplicateMatches.length} ${label}
                with this name. Please confirm this is not a duplicate before creating.</p>
                <ul class="text-left list-disc pl-5">${duplicateMatches.map(renderMatch).join('')}</ul>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Create anyway',
            cancelButtonText: 'Go back',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-lg',
                confirmButton: 'rounded-lg px-4 py-2',
                cancelButton: 'rounded-lg px-4 py-2',
            },
        }).then((result) => {
            if (result.isConfirmed) {
                ackInput.value = '1';
                studentForm.submit();
            }
        });
    }
});
