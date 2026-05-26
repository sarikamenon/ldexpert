document.addEventListener('DOMContentLoaded', () => {
    initSameAsFullName();
    initPrivateStudentToggle();
});

function initSameAsFullName() {
    const fullNameInput = document.getElementById('full_name');
    const displayNameInput = document.getElementById('display_name');
    const sameAsFullName = document.getElementById('same_as_full_name');

    if (!fullNameInput || !displayNameInput || !sameAsFullName) {
        return;
    }

    const syncDisplayName = () => {
        if (sameAsFullName.checked) {
            displayNameInput.value = fullNameInput.value;
        }
    };

    sameAsFullName.addEventListener('change', syncDisplayName);
    fullNameInput.addEventListener('input', syncDisplayName);

    // Typing directly in the NOVA name field breaks the link.
    displayNameInput.addEventListener('input', () => {
        if (displayNameInput.value !== fullNameInput.value) {
            sameAsFullName.checked = false;
        }
    });
}

function initPrivateStudentToggle() {
    const privateStudentCheckbox = document.getElementById('is_private_student');
    const autoExtendSection = document.getElementById('is_auto_extend_section');
    const autoExtendCheckbox = document.getElementById('is_auto_extend');

    if (!privateStudentCheckbox || !autoExtendSection || !autoExtendCheckbox) {
        return;
    }

    privateStudentCheckbox.addEventListener('change', function () {
        if (this.checked) {
            autoExtendSection.style.display = '';
            if (!autoExtendSection.dataset.everShown) {
                autoExtendCheckbox.checked = true;
            }
            autoExtendSection.dataset.everShown = '1';
        } else {
            autoExtendSection.style.display = 'none';
            autoExtendCheckbox.checked = false;
        }
    });
}
