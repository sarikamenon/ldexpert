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

    // Update type hidden, columns display, and download link visibility when import type changes
    const requiredColumnsList = document.getElementById('requiredColumnsList');
    const optionalColumnsList = document.getElementById('optionalColumnsList');
    const templateDownloadSection = document.getElementById('templateDownloadSection');
    const hideDownloadForTypes = ['RSM', 'MARVIN'];

    const templatesDataEl = document.getElementById('templatesData');
    const templates = templatesDataEl ? JSON.parse(templatesDataEl.textContent || '{}') : {};

    function renderColumns(container, columns, className) {
        if (!container) return;
        container.innerHTML = columns
            .map((col) => `<span class="px-2 py-1 ${className} text-xs rounded">${col}</span>`)
            .join('');
    }

    function updateTemplateDisplay() {
        const selectedType = importTypeSelect?.value || 'NOVA';
        if (typeHidden) {
            typeHidden.value = selectedType;
        }

        const template = templates[selectedType] || templates.NOVA || { required_columns: [], optional_columns: [] };
        const required = template.required_columns || [];
        const optional = template.optional_columns || [];

        renderColumns(requiredColumnsList, required, 'bg-primary/10 text-primary');
        renderColumns(optionalColumnsList, optional, 'bg-foreground/10 text-foreground/70');

        const optionalSection = document.getElementById('optionalColumnsSection');
        if (optionalSection) {
            optionalSection.classList.toggle('hidden', optional.length === 0);
        }
        if (templateDownloadSection) {
            templateDownloadSection.classList.toggle('hidden', hideDownloadForTypes.includes(selectedType));
        }
        if (templateDownloadLink && selectedType) {
            const url = new URL(templateDownloadLink.href);
            url.searchParams.set('type', selectedType);
            templateDownloadLink.href = url.toString();
        }
    }

    if (importTypeSelect) {
        importTypeSelect.addEventListener('change', updateTemplateDisplay);
        updateTemplateDisplay();
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
                        window.location.href = `/admin/ssas/imports/${data.data.import_id}`;
                    }, 1500);
                } else {
                    // Fallback to import history
                    setTimeout(() => {
                        window.location.href = '/admin/ssas/imports';
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
