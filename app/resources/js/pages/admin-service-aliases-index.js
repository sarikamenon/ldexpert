import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

async function initServiceAliasesTable() {
    const table = document.getElementById('serviceAliasesTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const form = document.getElementById('serviceAliasFiltersForm');

    try {
        await loadDataTablesLibrary();
        await initServerSideDataTable('#serviceAliasesTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: [2, 4] },
                { width: '90px', targets: 0 },
                { width: '120px', targets: 3 },
                { width: '90px', targets: 4 },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_source = form.querySelector('[name="source"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init service aliases table', error);
    }
}

function setupDeleteHandlers() {
    document.body.addEventListener('submit', async (event) => {
        const form = event.target.closest('form');
        if (!form) return;

        const deleteButton = form.querySelector('[data-confirm-title]');
        if (!deleteButton) return;

        event.preventDefault();

        const title = deleteButton.getAttribute('data-confirm-title') || 'Delete?';
        const text = deleteButton.getAttribute('data-confirm-text') || 'This action cannot be undone.';

        const result = await confirmDialog({
            title,
            text,
            icon: 'warning',
            confirmButtonText: 'Yes, delete',
        });

        if (!result.isConfirmed) return;

        try {
            showLoading('Deleting alias...');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(form.action, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            if (response.ok && data.success) {
                await successToast(data.message);
                reloadTable();
            } else {
                errorAlert(data.message || 'Failed to delete alias.');
            }
        } catch (error) {
            console.error('Failed to delete alias', error);
            errorAlert('An unexpected error occurred.');
        } finally {
            closeAlert();
        }
    });
}

function reloadTable() {
    if (typeof window.jQuery !== 'undefined') {
        const dt = window.jQuery('#serviceAliasesTable').DataTable();
        if (dt && dt.ajax && dt.ajax.reload) {
            dt.ajax.reload();
        } else {
            window.location.reload();
        }
    } else {
        window.location.reload();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('serviceAliasesTable')) return;

    initServiceAliasesTable();
    setupDeleteHandlers();

    const form = document.getElementById('serviceAliasFiltersForm');
    if (form) {
        form.addEventListener('change', () => reloadTable());
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            reloadTable();
        });
    }
});
