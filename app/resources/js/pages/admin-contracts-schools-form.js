import { errorAlert } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
    initServiceRows();
    initDocumentUpload();
});

function initServiceRows() {
    const tableBody = document.querySelector('#contractServicesTable tbody');
    const addButton = document.getElementById('addServiceRow');
    const template = document.getElementById('serviceRowTemplate');

    if (!tableBody || !addButton || !template) {
        return;
    }

    let rowIndex = tableBody.querySelectorAll('.service-row').length;

    addButton.addEventListener('click', () => {
        const templateHtml = template.innerHTML.trim().replace(/__INDEX__/g, String(rowIndex));
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = templateHtml;
        const newRow = wrapper.firstElementChild;
        tableBody.appendChild(newRow);
        rowIndex += 1;
    });

    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');

    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', () => {
            if (!startDateInput.value) {
                return;
            }
            const startDate = new Date(startDateInput.value);
            const year = startDate.getFullYear();
            endDateInput.value = `${year + 1}-05-31`;
        });
    }

    tableBody.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.remove-service-row');
        if (!removeButton) {
            return;
        }

        if (tableBody.querySelectorAll('.service-row').length === 1) {
            errorAlert('At least one service is required.');
            return;
        }

        removeButton.closest('tr').remove();
    });
}

function initDocumentUpload() {
    const existingDocName = document.getElementById('existing-document-name');
    const removeExistingBtn = document.getElementById('remove-existing-document');
    const removeDocInput = document.getElementById('remove_document_input');
    const documentInput = document.getElementById('document_input');
    const removeSelectedBtn = document.getElementById('remove-selected-document');

    if (removeExistingBtn && existingDocName && removeDocInput) {
        removeExistingBtn.addEventListener('click', () => {
            existingDocName.classList.add('hidden');
            removeDocInput.value = '1';
        });
    }

    if (documentInput && removeSelectedBtn) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];

        documentInput.addEventListener('change', () => {
            const hasFile = documentInput.files && documentInput.files.length > 0;

            if (hasFile) {
                const file = documentInput.files[0];

                if (file.size > maxSize) {
                    errorAlert('File size must not exceed 10MB.');
                    documentInput.value = '';
                    removeSelectedBtn.classList.add('hidden');
                    return;
                }

                if (!allowedTypes.includes(file.type)) {
                    errorAlert('Invalid file type. Accepted formats: PDF, DOC, DOCX, JPG, PNG.');
                    documentInput.value = '';
                    removeSelectedBtn.classList.add('hidden');
                    return;
                }
            }

            removeSelectedBtn.classList.toggle('hidden', !hasFile);
            if (hasFile && existingDocName) {
                existingDocName.classList.add('hidden');
            }
        });

        removeSelectedBtn.addEventListener('click', () => {
            documentInput.value = '';
            removeSelectedBtn.classList.add('hidden');
        });
    }
}
