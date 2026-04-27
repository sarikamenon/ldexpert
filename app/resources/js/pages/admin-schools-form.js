document.addEventListener('DOMContentLoaded', () => {
    const privateStudentCheckbox = document.getElementById('is_private_student');
    const autoExtendSection = document.getElementById('is_auto_extend_section');
    const autoExtendCheckbox = document.getElementById('is_auto_extend');

    if (!privateStudentCheckbox || !autoExtendSection || !autoExtendCheckbox) {
        return;
    }

    privateStudentCheckbox.addEventListener('change', function () {
        autoExtendSection.style.display = this.checked ? '' : 'none';
        if (!this.checked) {
            autoExtendCheckbox.checked = false;
        }
    });
});
