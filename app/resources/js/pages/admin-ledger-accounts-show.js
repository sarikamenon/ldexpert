import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { initLedgerAdjustmentForms } from '../common/ledger-adjustment-form';
import {
    closeAllAdjustmentModals,
    initAdjustmentModals,
} from '../common/ledger-adjustment-modals';
import { initEditAdjustmentFlow } from '../common/ledger-adjustment-edit';
import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

const TABLE_ID = 'ledgerTransactionsTable';

document.addEventListener('DOMContentLoaded', () => {
    initAdjustmentModals();

    const reloadAfterChange = () => {
        reloadLedgerTable();
        void reloadStats();
    };

    initLedgerAdjustmentForms({
        async onSuccess(data) {
            closeAllAdjustmentModals();
            await successToast(data.message || 'Saved successfully.');
            reloadAfterChange();
        },
    });

    initEditAdjustmentFlow({
        tableId: TABLE_ID,
        onAfterSave: reloadAfterChange,
    });

    initRowDeleteHandler(reloadAfterChange);

    const table = document.getElementById(TABLE_ID) || document.querySelector('.ledger-transactions-table');
    if (!table) {
        return;
    }

    void initTransactionsTable(table);
});

async function initTransactionsTable(table) {
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
        return;
    }

    await initDataTable('.ledger-transactions-table', {
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: -1 },
        ],
    });
}

function initRowDeleteHandler(onAfterDelete) {
    const table = document.getElementById(TABLE_ID);
    if (!table) {
        return;
    }

    // Delegated delete confirmation: ActionButtons::delete renders a <form> whose
    // form tag carries data-confirm-*. Intercept and confirm before submitting.
    document.body.addEventListener('submit', async (event) => {
        const form = event.target.closest('form');
        if (!form || !table.contains(form) || !form.hasAttribute('data-confirm-title')) {
            return;
        }

        event.preventDefault();
        await confirmAndDelete(form, onAfterDelete);
    });
}

async function confirmAndDelete(form, onAfterDelete) {
    const title = form.getAttribute('data-confirm-title') || 'Delete?';
    const text = form.getAttribute('data-confirm-text') || 'This action cannot be undone.';

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
        if (typeof onAfterDelete === 'function') {
            onAfterDelete();
        }
    } catch (err) {
        console.error('Delete failed', err);
        await errorAlert('An unexpected error occurred. Please try again.');
    }
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

// DataTables' ajax.reload() API is jQuery-only — no vanilla equivalent exists
// in the bundled dist build. Fall back to a full page reload if jQuery isn't
// available, which keeps the user on the same page even if the table can't
// soft-refresh.
function reloadLedgerTable() {
    const $ = window.jQuery;
    const tableEl = document.getElementById(TABLE_ID);
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
