import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';
import { setupStatusChanges } from '../common/status-change';
import Swal from 'sweetalert2';

async function initSSATable() {
    try {
        await loadDataTablesLibrary();
        await initDataTable('#ssasTable', {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
        });
    } catch (error) {
        console.error('Failed to init SSAs table', error);
    }
}

// Fetch therapists filtered by service IDs
async function fetchFilteredTherapists(serviceIds, csrfToken) {
    const urlEl = document.getElementById('therapists-for-service-url');
    if (!urlEl) return null;

    const baseUrl = JSON.parse(urlEl.textContent);
    const params = new URLSearchParams();
    serviceIds.forEach((id) => params.append('service_ids[]', id));
    const url = serviceIds.length ? `${baseUrl}?${params.toString()}` : baseUrl;

    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });

    if (!response.ok) return null;
    return response.json();
}

// Show the assign therapist modal after fetching filtered therapists
async function showAssignModal(ssaId, serviceIds, csrfToken) {
    showLoading('Loading therapists...');
    const therapists = await fetchFilteredTherapists(serviceIds, csrfToken);
    closeAlert();

    if (!therapists || therapists.length === 0) {
        errorAlert('No therapists available for the services in this SSA.');
        return;
    }

    const inputOptions = new Map();
    therapists.forEach((t) => inputOptions.set(String(t.id), t.name));

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
            body: JSON.stringify({ therapist_id: result.value }),
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
}

// Status changes are now handled by the common module

function setupAssignmentActions() {
    const assignBtn = document.getElementById('assignTherapistBtn');
    const unassignBtn = document.getElementById('unassignTherapistBtn');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!assignBtn && !unassignBtn) {
        return;
    }

    if (assignBtn) {
        assignBtn.addEventListener('click', async () => {
            const ssaId = assignBtn.dataset.ssaId;
            const serviceIdsEl = document.getElementById('ssa-service-ids');
            const serviceIds = serviceIdsEl ? JSON.parse(serviceIdsEl.textContent) : [];
            await showAssignModal(ssaId, serviceIds, csrfToken);
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
                    body: JSON.stringify({ reason: result.value || null }),
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

    if (assignButtons.length === 0) {
        return;
    }

    assignButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const ssaId = button.dataset.ssaId;
            const serviceIds = JSON.parse(button.dataset.serviceIds || '[]');
            await showAssignModal(ssaId, serviceIds, csrfToken);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ssasTable')) {
        initSSATable();
    }

    setupStatusChanges('ssa', '.change-status-btn', { idAttribute: 'ssa-id' });
    setupAssignmentActions();
    setupListAssignmentActions();
});

