import { confirmDialog } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
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
                icon: button.dataset.confirmIcon || 'warning',
                confirmButtonText: 'Yes, delete',
            });

            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
