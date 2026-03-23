import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

async function initExpensesTable() {
    const table = document.getElementById('expensesTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('expensesFiltersForm');

        const dt = await initServerSideDataTable('#expensesTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_category_id = form.querySelector('[name="category_id"]')?.value ?? '';
                d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
            },
        });

        if (dt && dt.on) {
            dt.on('xhr.dt', function (e, settings, json) {
                const totalEl = document.getElementById('expensesTotalAmount');
                const countEl = document.getElementById('expensesCount');
                if (totalEl) {
                    totalEl.textContent = Number(json.totalAmount != null ? json.totalAmount : 0).toFixed(2);
                }
                if (countEl) {
                    countEl.textContent = json.recordsFiltered != null ? json.recordsFiltered : (json.recordsTotal || 0);
                }
            });
        }

        if (form && typeof window.jQuery !== 'undefined') {
            form.addEventListener('change', () => {
                const dataTable = window.jQuery('#expensesTable').DataTable();
                if (dataTable && dataTable.ajax && dataTable.ajax.reload) {
                    dataTable.ajax.reload();
                }
            });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const dataTable = window.jQuery('#expensesTable').DataTable();
                if (dataTable && dataTable.ajax && dataTable.ajax.reload) {
                    dataTable.ajax.reload();
                }
            });
        }
    } catch (error) {
        console.error('Failed to init expenses table', error);
    }
}

window.jQuery(function ($) {
    const $table = $('#expensesTable');
    if (!$table.length) {
        return;
    }

    void initExpensesTable();

    $(document).on('click', 'form.expense-delete-form button[type="submit"]', async function (e) {
        e.preventDefault();
        const $form = $(this).closest('form');
        const result = await confirmDialog({
            title: 'Delete expense?',
            text: 'Are you sure you want to delete this expense?',
            icon: 'warning',
            confirmButtonText: 'Yes, delete',
        });
        if (result.isConfirmed) {
            $form[0].submit();
        }
    });
});
