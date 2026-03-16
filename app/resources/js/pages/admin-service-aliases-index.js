import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

function getFilterForm() {
    return document.getElementById('serviceAliasFiltersForm');
}

async function initServiceAliasesTable() {
    const table = document.getElementById('serviceAliasesTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();
        await initServerSideDataTable('#serviceAliasesTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            searchDelay: 1000,
            columnDefs: [
                { orderable: false, targets: [2, 4] },
                { width: '90px', targets: 0 },
                { width: '120px', targets: 3 },
                { width: '90px', targets: 4 },
            ],
            getExtraData(d) {
                const form = getFilterForm();
                const formSearch = form?.querySelector('[name="search"]')?.value ?? '';
                const dtSearch = (d.search?.value ?? '').trim();
                d.filter_search = formSearch || dtSearch;
                if (form) {
                    d.filter_source = form.querySelector('[name="source"]')?.value ?? '';
                }
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

function setupFilterHandlers() {
    const form = getFilterForm();
    if (!form) return;

    form.addEventListener('change', () => reloadTable());
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        reloadTable();
    });

    const searchInput = form.querySelector('[name="search"]');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => reloadTable(), 400);
        });
    }
}

function init() {
    if (!document.getElementById('serviceAliasesTable')) return;

    initServiceAliasesTable();
    setupDeleteHandlers();
    setupFilterHandlers();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
