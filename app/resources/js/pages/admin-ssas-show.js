import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';
import { setupStatusChanges } from '../common/status-change';
import Swal from 'sweetalert2';

// Initialize delivery progress chart
function initDeliveryProgressChart() {
    const canvas = document.getElementById('deliveryProgressChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const servedMinutes = parseInt(canvas.dataset.served || '0');
    const thoMinutes = parseInt(canvas.dataset.tho || '0');
    const remainingMinutes = Math.max(0, thoMinutes - servedMinutes);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Served Minutes', 'Remaining Minutes'],
            datasets: [{
                data: [servedMinutes, remainingMinutes],
                backgroundColor: [
                    '#14b8a6', // Teal (secondary) for served
                    '#e5e7eb', // Gray for remaining
                ],
                borderWidth: 2,
                borderColor: '#ffffff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value.toLocaleString()} minutes (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Setup assignment actions
function setupAssignmentActions() {
    const assignBtn = document.getElementById('assignTherapistBtn');
    const unassignBtn = document.getElementById('unassignTherapistBtn');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (assignBtn) {
        assignBtn.addEventListener('click', async () => {
            const ssaId = assignBtn.dataset.ssaId;

            // Get therapists from a hidden select or fetch from page
            const therapistSelect = document.getElementById('therapist_select_for_assignment');
            if (!therapistSelect) {
                errorAlert('Therapist list not available. Please refresh the page.');
                return;
            }

            // Build options object for SweetAlert2
            const inputOptions = new Map();
            Array.from(therapistSelect.options).forEach((option) => {
                if (option.value) {
                    inputOptions.set(option.value, option.text);
                }
            });

            const result = await Swal.fire({
                title: 'Assign Therapist?',
                text: 'Please select a therapist to assign.',
                icon: 'question',
                input: 'select',
                inputOptions: inputOptions,
                inputPlaceholder: 'Select a therapist',
                showCancelButton: true,
                confirmButtonText: 'Assign',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                customClass: {
                    popup: 'rounded-lg',
                    confirmButton: 'rounded-lg px-4 py-2',
                    cancelButton: 'rounded-lg px-4 py-2',
                },
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            try {
                showLoading('Assigning therapist...');
                const response = await fetch(`/admin/ssas/${ssaId}/assign-therapist`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        therapist_id: result.value,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    await successToast(data.message);
                    window.location.reload();
                } else {
                    errorAlert(data.message || 'Failed to assign therapist.');
                }
            } catch (error) {
                console.error('Failed to assign therapist', error);
                errorAlert('An unexpected error occurred.');
            } finally {
                closeAlert();
            }
        });
    }

    if (unassignBtn) {
        unassignBtn.addEventListener('click', async () => {
            const ssaId = unassignBtn.dataset.ssaId;

            const result = await confirmDialog({
                title: 'Unassign Therapist?',
                text: 'You are about to unassign the therapist from this SSA.',
                icon: 'warning',
                confirmButtonText: 'Yes, unassign',
                showInput: true,
                inputPlaceholder: 'Reason (optional)',
                inputValidator: () => null, // Optional reason
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                showLoading('Unassigning therapist...');
                const response = await fetch(`/admin/ssas/${ssaId}/unassign-therapist`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        reason: result.value || null,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    await successToast(data.message);
                    window.location.reload();
                } else {
                    errorAlert(data.message || 'Failed to unassign therapist.');
                }
            } catch (error) {
                console.error('Failed to unassign therapist', error);
                errorAlert('An unexpected error occurred.');
            } finally {
                closeAlert();
            }
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initDeliveryProgressChart();
    setupStatusChanges('ssa', '.change-status-btn', { idAttribute: 'ssa-id' });
    setupAssignmentActions();
});

