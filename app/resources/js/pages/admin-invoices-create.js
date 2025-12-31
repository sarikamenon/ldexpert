/**
 * Invoice Create Page JavaScript
 * Handles session log selection, select all/deselect all, and summary calculation
 */

import { errorAlert } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const checkboxes = document.querySelectorAll('.session-log-checkbox');
    const summary = document.getElementById('sessionLogsSummary');
    const selectedCount = document.getElementById('selectedCount');
    const selectedTotal = document.getElementById('selectedTotal');
    const form = document.getElementById('createInvoiceForm');

    if (!form) {
        return;
    }

    function updateSummary() {
        const selected = Array.from(checkboxes).filter(cb => cb.checked);
        const count = selected.length;
        const total = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount || 0), 0);

        if (selectedCount) {
            selectedCount.textContent = count;
        }
        if (selectedTotal) {
            selectedTotal.textContent = total.toFixed(2);
        }
        if (summary) {
            summary.classList.toggle('hidden', count === 0);
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateSummary();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => {
                cb.checked = true;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = true;
            }
            updateSummary();
        });
    }

    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => {
                cb.checked = false;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            updateSummary();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            updateSummary();
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = Array.from(checkboxes).every(c => c.checked);
            }
        });
    });

    form.addEventListener('submit', async function (e) {
        const selected = Array.from(checkboxes).filter(cb => cb.checked);
        if (selected.length === 0) {
            e.preventDefault();
            await errorAlert('Please select at least one session log to create an invoice.');
            return false;
        }
    });
});

