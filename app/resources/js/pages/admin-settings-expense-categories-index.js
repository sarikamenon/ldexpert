import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

async function initExpenseCategoriesTable() {
    const table = document.getElementById('expenseCategoriesTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('expenseCategoriesFiltersForm');

        await initServerSideDataTable('#expenseCategoriesTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
            },
        });

        if (form && typeof window.jQuery !== 'undefined') {
            form.addEventListener('change', () => {
                const dt = window.jQuery('#expenseCategoriesTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const dt = window.jQuery('#expenseCategoriesTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });
        }
    } catch (error) {
        console.error('Failed to init expense categories table', error);
    }
}

const BADGE_ACTIVE = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-success/10 text-success border border-success/20">Active</span>';
const BADGE_INACTIVE = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-background/subtle text-foreground border border-border">Inactive</span>';
const ICON_ACTIVE = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>';
const ICON_INACTIVE = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';

window.jQuery(function ($) {
    const $table = $('#expenseCategoriesTable');
    if (!$table.length) {
        return;
    }

    void initExpenseCategoriesTable();

    $table.on('click', '.toggle-expense-category-status', async function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }
        const url = $btn.data('toggle-url');
        const currentStatus = $btn.data('status');
        const nextActive = currentStatus !== 'active';
        const action = nextActive ? 'Activate' : 'Deactivate';

        const result = await confirmDialog({
            title: `${action} category?`,
            text: nextActive ? 'This category will be available when creating or editing expenses.' : 'This category will be hidden from new expenses.',
            icon: 'warning',
            confirmButtonText: `Yes, ${action.toLowerCase()}`,
        });

        if (!result.isConfirmed) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            errorAlert('Security token not found. Please refresh the page.');
            return;
        }

        $btn.prop('disabled', true);
        try {
            showLoading('Updating status...');
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                await successToast(data.message);
                const dt = $table.DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            } else {
                errorAlert(data.message || 'Failed to update category status.');
            }
        } catch (err) {
            console.error('Toggle expense category status failed', err);
            errorAlert('An unexpected error occurred.');
        } finally {
            closeAlert();
            $btn.prop('disabled', false);
        }
    });
});
