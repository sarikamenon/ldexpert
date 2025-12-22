import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

(function ($) {
    'use strict';

    $(document).ready(function () {
        const $pendingScheduleList = $('#pendingScheduleList');

        if (!$pendingScheduleList.length) {
            return;
        }

        // Handle billing status update (legacy support for schedule cards)
        $pendingScheduleList.on('click', '.schedule-bill-btn', async function () {
            const $btn = $(this);
            const scheduleId = $btn.data('schedule-id') || 
                              $btn.closest('.border').find('.schedule-edit-btn').data('schedule-id');

            if (!scheduleId) {
                errorAlert('Schedule ID not found.');
                return;
            }

            const result = await confirmDialog({
                title: 'Update Billing Status?',
                text: 'Please select the billing status for this schedule.',
                icon: 'question',
                confirmButtonText: 'Mark as Billed',
                showCancelButton: true,
                cancelButtonText: 'Cancel',
                input: 'select',
                inputOptions: {
                    'billed': 'Billed',
                    'not_billable': 'Not Billable',
                    'waived': 'Waived',
                },
                inputPlaceholder: 'Select billing status',
                inputValidator: (value) => {
                    if (!value) {
                        return 'You need to select a billing status';
                    }
                },
            });

            if (!result.isConfirmed) {
                return;
            }

            const billingStatus = result.value;

            showLoading('Updating billing status...');

            try {
                const response = await fetch(`/therapist/schedule/${scheduleId}/billing-status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        billing_status: billingStatus,
                    }),
                });

                closeAlert();

                if (!response.ok) {
                    const error = await response.json().catch(() => ({ message: 'Failed to update billing status' }));
                    throw new Error(error.message || 'Failed to update billing status');
                }

                const data = await response.json();
                successToast('Billing status updated successfully!');
                
                // Reload the page to reflect changes
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } catch (error) {
                errorAlert(error.message || 'An error occurred while updating billing status');
            }
        });

        // Handle delete schedule from list view
        $pendingScheduleList.on('click', '.schedule-delete-btn', async function () {
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const scheduleId = $row.data('schedule-id');

            if (!scheduleId) {
                errorAlert('Schedule ID not found.');
                return;
            }

            const result = await confirmDialog({
                title: 'Delete Schedule?',
                text: 'This will remove the schedule from your calendar. This action cannot be undone.',
                icon: 'warning',
                confirmButtonText: 'Yes, delete it',
                showCancelButton: true,
                cancelButtonText: 'Cancel',
            });

            if (!result.isConfirmed) {
                return;
            }

            showLoading('Deleting schedule...');

            try {
                const response = await fetch(`/therapist/schedule/${scheduleId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                closeAlert();

                if (!response.ok) {
                    const error = await response.json().catch(() => ({ message: 'Failed to delete schedule' }));
                    throw new Error(error.message || 'Failed to delete schedule');
                }

                $row.remove();
                successToast('Schedule deleted successfully.');
            } catch (error) {
                errorAlert(error.message || 'An error occurred while deleting the schedule');
            }
        });
    });
})(window.jQuery);

