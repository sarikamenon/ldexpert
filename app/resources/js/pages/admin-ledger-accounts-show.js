import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { initLedgerAdjustmentForms } from '../common/ledger-adjustment-form';
import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

window.jQuery(function () {
    initAdjustmentModals();
    initLedgerAdjustmentForms({
        async onSuccess(data) {
            closeAllAdjustmentModals();
            await successToast(data.message || 'Saved successfully.');
            reloadLedgerTable();
            void reloadStats();
        },
    });
    initRowActions();

    const table = document.getElementById('ledgerTransactionsTable') || document.querySelector('.ledger-transactions-table');
    if (!table) {
        return;
    }

    void (async function init() {
        await loadDataTablesLibrary();
        const dataUrl = table.getAttribute('data-datatable-url');
        if (dataUrl) {
            const selector = table.id ? `#${table.id}` : '.ledger-transactions-table';
            await initServerSideDataTable(selector, dataUrl, {
                order: [[0, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [2, 3, 4, 5, 7, 8] },
                ],
                getExtraData(d) {
                    d.filter_type = table.getAttribute('data-filter-type') || '';
                    d.filter_id = table.getAttribute('data-filter-id') || '';
                },
            });
        } else {
            await initDataTable('.ledger-transactions-table', {
                order: [[0, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: -1 },
                ],
            });
        }
    })();
});

function initAdjustmentModals() {
    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-open-modal');
            openModal(id);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-close-modal');
            closeModal(id);
        });
    });

    document.querySelectorAll('[data-ledger-adjustment-cancel]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('[data-ledger-adjustment-modal]');
            if (modal && modal.id) {
                closeModal(modal.id);
            }
        });
    });

    document.querySelectorAll('[data-ledger-adjustment-modal]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllAdjustmentModals();
        }
    });
}

function openModal(id) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    el.classList.remove('hidden');
    el.classList.add('flex');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    el.classList.add('hidden');
    el.classList.remove('flex');
    resetCreateAdjustmentForm(el);
}

function closeAllAdjustmentModals() {
    document.querySelectorAll('[data-ledger-adjustment-modal]').forEach((modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        resetCreateAdjustmentForm(modal);
    });
}

// Reset create-adjustment forms (credit note / refund) when their modal closes
// so reopening shows a clean form with today's date. The edit modal is excluded —
// its values are populated from the API on open.
function resetCreateAdjustmentForm(modal) {
    const form = modal.querySelector('form[data-ledger-adjustment-form]');
    if (!form) {
        return;
    }
    form.reset();
    const dateInput = form.querySelector('input[name="recorded_at"]');
    if (dateInput) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }
    clearFieldErrors(form);
}

function initRowActions() {
    const table = document.getElementById('ledgerTransactionsTable');
    if (!table) {
        return;
    }

    // Delegated edit click — DataTables re-renders rows on every page/sort/filter change.
    table.addEventListener('click', (event) => {
        const editLink = event.target.closest('[data-edit-adjustment]');
        if (editLink) {
            event.preventDefault();
            void openEditModal(editLink.getAttribute('data-fetch-url'));
        }
    });

    // Delegated delete confirmation: ActionButtons::delete renders a <form> whose
    // form tag carries data-confirm-*. Intercept and confirm before submitting.
    document.body.addEventListener('submit', async (event) => {
        const form = event.target.closest('form');
        if (!form || !table.contains(form) || !form.hasAttribute('data-confirm-title')) {
            return;
        }

        event.preventDefault();
        await confirmAndDelete(form, form);
    });

    initEditFormSubmit();
}

async function openEditModal(fetchUrl) {
    if (!fetchUrl) {
        return;
    }

    try {
        const response = await fetch(fetchUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.success === false) {
            await errorAlert(data.message || 'Could not load adjustment.');
            return;
        }

        const form = document.getElementById('editAdjustmentForm');
        if (!form || !data.entry) {
            return;
        }

        // GET show and PUT update share the same path; just point the form at it.
        form.action = fetchUrl;
        form.dataset.entryId = data.entry.id;

        document.getElementById('editAdjustmentRecordedAt').value = data.entry.recorded_at || '';
        document.getElementById('editAdjustmentAmount').value = data.entry.amount ?? '';
        document.getElementById('editAdjustmentNotes').value = data.entry.notes ?? '';

        clearFieldErrors(form);
        openModal('editAdjustmentModal');
    } catch (err) {
        console.error('Failed to load adjustment', err);
        await errorAlert('Could not load adjustment. Please try again.');
    }
}

function initEditFormSubmit() {
    const form = document.getElementById('editAdjustmentForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalLabel = submitBtn ? submitBtn.innerHTML : null;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving…';
        }

        try {
            const formData = new FormData(form);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || formData.get('_token');

            const response = await fetch(form.action, {
                method: 'POST', // Laravel reads _method=PUT from the form
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                renderValidationErrors(form, data.errors || {});
                return;
            }

            if (!response.ok || data.success === false) {
                await errorAlert(data.message || 'Could not update adjustment.');
                return;
            }

            closeAllAdjustmentModals();
            await successToast(data.message || 'Adjustment updated.');
            reloadLedgerTable();
            void reloadStats();
        } catch (err) {
            console.error('Edit submit failed', err);
            await errorAlert('An unexpected error occurred. Please try again.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                if (originalLabel !== null) {
                    submitBtn.innerHTML = originalLabel;
                }
            }
        }
    });
}

async function confirmAndDelete(form, deleteButton) {
    const title = deleteButton.getAttribute('data-confirm-title') || 'Delete?';
    const text = deleteButton.getAttribute('data-confirm-text') || 'This action cannot be undone.';

    const result = await confirmDialog({
        title,
        text,
        icon: 'warning',
        confirmButtonText: 'Yes, delete',
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const formData = new FormData(form);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || formData.get('_token');

        const response = await fetch(form.action, {
            method: 'POST', // Laravel reads _method=DELETE from the form
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.success === false) {
            await errorAlert(data.message || 'Could not delete adjustment.');
            return;
        }

        await successToast(data.message || 'Adjustment deleted.');
        reloadLedgerTable();
        void reloadStats();
    } catch (err) {
        console.error('Delete failed', err);
        await errorAlert('An unexpected error occurred. Please try again.');
    }
}

function clearFieldErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

function renderValidationErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const target = form.querySelector(`[data-error-for="${field}"]`);
        if (target) {
            target.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            target.classList.remove('hidden');
        }
    });
}

async function reloadStats() {
    const container = document.getElementById('ledgerAccountStats');
    const url = container?.getAttribute('data-stats-url');
    if (!container || !url) {
        return;
    }

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false || typeof data.html !== 'string') {
            return;
        }
        container.innerHTML = data.html;
    } catch (err) {
        console.error('Failed to refresh stats', err);
    }
}

// TODO: migrate to vanilla JS once a vanilla DataTables reload helper exists in
// resources/js/common/datatables.js. DataTables' ajax.reload() API is jQuery-only.
function reloadLedgerTable() {
    const $ = window.jQuery;
    const tableEl = document.getElementById('ledgerTransactionsTable');
    if (!$ || !tableEl || !$.fn || !$.fn.DataTable) {
        window.location.reload();
        return;
    }

    const api = $.fn.DataTable.isDataTable(tableEl) ? $(tableEl).DataTable() : null;
    if (!api) {
        window.location.reload();
        return;
    }

    // false = stay on current page
    api.ajax.reload(null, false);
}
