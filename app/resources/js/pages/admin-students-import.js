import { errorAlert, successToast } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('importForm');
    const submitButton = document.getElementById('submitButton');
    const submitButtonText = document.getElementById('submitButtonText');
    const submitButtonSpinner = document.getElementById('submitButtonSpinner');
    const importTypeSelect = document.getElementById('import_type');
    const typeHidden = document.getElementById('type');
    const templateDownloadLink = document.getElementById('templateDownloadLink');

    if (!form) {
        return;
    }

    // Update template download link when type changes
    if (importTypeSelect && templateDownloadLink) {
        importTypeSelect.addEventListener('change', (e) => {
            const selectedType = e.target.value;
            typeHidden.value = selectedType;
            const url = new URL(templateDownloadLink.href);
            url.searchParams.set('type', selectedType);
            templateDownloadLink.href = url.toString();
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const fileInput = document.getElementById('file');
        const importType = document.getElementById('import_type');

        // Validate file selection
        if (!fileInput.files || fileInput.files.length === 0) {
            errorAlert('Please select a CSV file to import.');
            return;
        }

        // Validate import type
        if (!importType || !importType.value) {
            errorAlert('Please select an import type.');
            return;
        }

        // Set type in form data
        formData.set('type', importType.value);

        // Show loading state
        submitButton.disabled = true;
        submitButtonText.classList.add('hidden');
        submitButtonSpinner.classList.remove('hidden');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Import failed. Please check the file and try again.');
            }

            if (data.success) {
                successToast('Import queued successfully. You will receive an email notification when it completes.');
                
                // Redirect to import status page
                if (data.data && data.data.import_id) {
                    setTimeout(() => {
                        window.location.href = `/admin/students/imports/${data.data.import_id}`;
                    }, 1500);
                } else {
                    // Fallback to import history
                    setTimeout(() => {
                        window.location.href = '/admin/students/imports';
                    }, 1500);
                }
            } else {
                errorAlert(data.message || 'Import failed. Please check the file and try again.');
            }
        } catch (error) {
            console.error('Import error:', error);
            errorAlert(error.message || 'An error occurred while importing. Please try again.');
        } finally {
            // Reset button state
            submitButton.disabled = false;
            submitButtonText.classList.remove('hidden');
            submitButtonSpinner.classList.add('hidden');
        }
    });
});
