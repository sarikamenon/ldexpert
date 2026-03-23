import { errorAlert } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
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
});

