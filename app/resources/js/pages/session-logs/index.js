import { initDataTable, loadDataTablesLibrary } from '../../common/datatables';
import { confirmDialog } from '../../common/sweetalert';

const bindConfirmations = () => {
    document.querySelectorAll('button[data-confirm-title]').forEach((button) => {
        const form = button.closest('form');
        if (!form || button.dataset.confirmBound === 'true') {
            return;
        }

        button.dataset.confirmBound = 'true';

        button.addEventListener('click', async (event) => {
            event.preventDefault();

            const result = await confirmDialog({
                title: button.dataset.confirmTitle || 'Are you sure?',
                text: button.dataset.confirmText || '',
                icon: button.dataset.confirmIcon || 'question',
                confirmButtonText: 'Yes',
            });

            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
};

document.addEventListener('DOMContentLoaded', async () => {
    await loadDataTablesLibrary();
    initDataTable('.session-log-table', {
        order: [[0, 'desc']],
    });

    bindConfirmations();
});

