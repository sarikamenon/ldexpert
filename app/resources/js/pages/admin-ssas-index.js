import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';
import { setupStatusChanges } from '../common/status-change';
import Swal from 'sweetalert2';

async function initSSATable() {
    const table = document.getElementById('ssasTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const form = document.getElementById('ssaFiltersForm');

    try {
        await loadDataTablesLibrary();
        await initServerSideDataTable('#ssasTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_student_id = form.querySelector('[name="student_id"]')?.value ?? '';
                d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                d.filter_service_id = form.querySelector('[name="service_id"]')?.value ?? '';
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init SSAs table', error);
    }
}

// Status changes are now handled by the common module

function setupAssignmentActions() {
    const assignBtn = document.getElementById('assignTherapistBtn');
    const unassignBtn = document.getElementById('unassignTherapistBtn');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Only proceed if assignment buttons exist (they're only on SSA show page)
    if (!assignBtn && !unassignBtn) {
        return;
    }

    // Check if therapist select exists
    const therapistSelect = document.getElementById('therapist_select_for_assignment');
    if (!therapistSelect) {
        // Silently return if therapist select doesn't exist
        return;
    }

    if (assignBtn) {
        assignBtn.addEventListener('click', async () => {
            const ssaId = assignBtn.dataset.ssaId;

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

function setupListAssignmentActions() {
    const assignButtons = document.querySelectorAll('.assign-therapist-btn');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Only proceed if there are assignment buttons on the page
    if (assignButtons.length === 0) {
        return;
    }

    // Check if therapist select exists before setting up actions
    const therapistSelect = document.getElementById('therapist_select_for_assignment');
    if (!therapistSelect) {
        // Silently return if therapist select doesn't exist (might be on a different page)
        return;
    }

    assignButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const ssaId = button.dataset.ssaId;

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
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ssasTable')) {
        initSSATable();
    }

    const form = document.getElementById('ssaFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#ssasTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#ssasTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }

    setupStatusChanges('ssa', '.change-status-btn', { idAttribute: 'ssa-id' });
    setupAssignmentActions();
    setupListAssignmentActions();
});

