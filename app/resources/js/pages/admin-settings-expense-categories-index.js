import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

window.jQuery(function ($) {
    const $table = $('.expense-categories-table');
    if (!$table.length) {
        return;
    }

    void (async function init() {
        await loadDataTablesLibrary();
        await initDataTable('.expense-categories-table', {
            order: [[0, 'asc']],
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
        });
    })();

    $(document).on('click', 'form.expense-category-delete-form button[type="submit"]', async function (e) {
        e.preventDefault();
        const $form = $(this).closest('form');
        const result = await confirmDialog({
            title: 'Delete category?',
            text: 'Are you sure you want to delete this category?',
            icon: 'warning',
            confirmButtonText: 'Yes, delete',
        });
        if (result.isConfirmed) {
            $form[0].submit();
        }
    });
});
