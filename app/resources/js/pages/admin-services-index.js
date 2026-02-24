import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

async function initServicesTable() {
    const table = document.getElementById('servicesTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const form = document.getElementById('serviceFiltersForm');

    try {
        await loadDataTablesLibrary();
        await initServerSideDataTable('#servicesTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_is_frequency_service = form.querySelector('[name="is_frequency_service"]')?.value ?? '';
                d.filter_is_direct_service = form.querySelector('[name="is_direct_service"]')?.value ?? '';
                d.filter_is_billable = form.querySelector('[name="is_billable"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init services table', error);
    }
}

function setupStatusToggles() {
    const buttons = document.querySelectorAll('.toggle-service-status');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const serviceId = button.dataset.service;
            const currentStatus = button.dataset.status;
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = nextStatus === 'active' ? 'activate' : 'deactivate';

            const result = await confirmDialog({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Service?`,
                text: `You are about to ${action} this service.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${action}`,
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                showLoading('Updating service status...');
                const response = await fetch(`/admin/services/${serviceId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ status: nextStatus }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    await successToast(data.message);
                    if (typeof window.jQuery !== 'undefined') {
                        const dt = window.jQuery('#servicesTable').DataTable();
                        if (dt && dt.ajax && dt.ajax.reload) {
                            dt.ajax.reload();
                        } else {
                            window.location.reload();
                        }
                    } else {
                        window.location.reload();
                    }
                } else {
                    errorAlert(data.message || 'Failed to update service status.');
                }
            } catch (error) {
                console.error('Failed to update service status', error);
                errorAlert('An unexpected error occurred.');
            } finally {
                closeAlert();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('servicesTable')) {
        return;
    }

    initServicesTable();
    setupStatusToggles();

    const form = document.getElementById('serviceFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#servicesTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#servicesTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});


