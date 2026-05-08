import { confirmDialog, successToast, errorAlert } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.ssa-goal-status-btn');

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const {
                statusUrl,
                status,
                confirmTitle,
                confirmText,
                confirmButton,
                confirmIcon,
                successMessage,
            } = button.dataset;

            if (!csrfToken || !statusUrl || !status) {
                return;
            }

            const result = await confirmDialog({
                title: confirmTitle,
                text: confirmText,
                icon: confirmIcon,
                confirmButtonText: confirmButton,
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('_method', 'PATCH');
                formData.append('_token', csrfToken);
                formData.append('status', status);

                const response = await fetch(statusUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json, text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok && response.status !== 302) {
                    throw new Error(`Request failed with status ${response.status}`);
                }

                await successToast(successMessage);
                window.location.reload();
            } catch (error) {
                errorAlert('Could not update the goal status. Please try again.');
            }
        });
    });
});
