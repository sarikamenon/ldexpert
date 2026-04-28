document.addEventListener('DOMContentLoaded', () => {
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
});
