import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

window.jQuery(function ($) {
    const $table = $('.expenses-table');
    if (!$table.length) {
        return;
    }

    void (async function init() {
        await loadDataTablesLibrary();
        await initDataTable('.expenses-table', {
            order: [[0, 'desc']],
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
        });
    })();

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
