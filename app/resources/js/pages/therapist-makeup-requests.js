import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';
import { openScheduleDetailsModal } from '../common/schedule-modal';

const SCHEDULE_DETAILS_URL = '/therapist/schedule';

let dataTableInstance = null;

async function initMakeupRequestsTable() {
    const table = document.getElementById('makeupRequestsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    await loadDataTablesLibrary();

    const form = document.getElementById('makeup-filter-form');

    dataTableInstance = await initServerSideDataTable('#makeupRequestsTable', dataUrl, {
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: [1, 2, 3, 4, 5, 6] }],
        getExtraData(d) {
            if (!form) return;
            d.filter_status = form.querySelector('[name="filter_status"]')?.value ?? '';
        },
    });
}

function bindStatusFilter() {
    const form = document.getElementById('makeup-filter-form');
    if (!form) return;

    const reload = () => dataTableInstance?.ajax.reload();

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        reload();
    });

    const clearBtn = form.querySelector('[data-filter-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            window.setTimeout(reload, 0);
        });
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const MODAL_SPINNER_HTML = `
    <div class="flex flex-col items-center justify-center py-16 text-foreground/60">
        <svg class="h-8 w-8 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <p class="mt-3 text-sm">Loading…</p>
    </div>
`;

async function openDetail(url) {
    const content = document.getElementById('makeup-modal-content');
    if (!content) return;

    content.innerHTML = MODAL_SPINNER_HTML;
    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'makeup-request-detail' }));

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to load details.');
        }

        content.innerHTML = await response.text();
    } catch (error) {
        errorAlert(error instanceof Error ? error.message : 'Failed to load details.');
    }
}

function bindScheduleDetailsHandler() {
    document.body.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-schedule-id]');
        if (!trigger) return;

        const scheduleId = Number(trigger.getAttribute('data-schedule-id'));
        if (!scheduleId) return;

        event.preventDefault();
        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'makeup-request-detail' }));
        openScheduleDetailsModal(scheduleId, SCHEDULE_DETAILS_URL, {
            editUrl: (id) => `/therapist/schedule/${id}/edit`,
            billUrl: (id) => `/therapist/session-logs/create/schedule/${id}`,
        });
    });
}

function bindViewHandler() {
    document.body.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-makeup-view-url]');
        if (!button) return;

        event.preventDefault();
        const url = button.dataset.makeupViewUrl;
        if (!url) return;

        openDetail(url);
    });
}

const requiredReason = (message) => (value) => (value && value.trim() ? null : message);

const POST_ACTIONS = [
    {
        attr: 'data-makeup-decline-url',
        confirm: {
            title: 'Decline Make-Up Request?',
            text: 'This will mark the request as declined on behalf of the parent.',
            icon: 'warning',
            confirmButtonText: 'Yes, decline',
            showInput: true,
            inputPlaceholder: 'Reason for declining (required)',
            inputValidator: requiredReason('Please provide a reason for declining.'),
        },
        buildBody: (reason) => {
            const formData = new FormData();
            formData.append('reason', reason);
            return formData;
        },
        loading: 'Declining...',
        success: 'Make-up request declined.',
        errorFallback: 'Failed to decline.',
    },
    {
        attr: 'data-makeup-not-required-url',
        confirm: {
            title: 'Mark as Not Required?',
            text: 'Use this when the original session is still happening for this student, so no make-up is needed. The parent will not be contacted.',
            icon: 'question',
            confirmButtonText: 'Yes, not required',
            showInput: true,
            inputPlaceholder: 'Reason (required)',
            inputValidator: requiredReason('Please provide a reason.'),
        },
        buildBody: (reason) => {
            const formData = new FormData();
            formData.append('reason', reason);
            return formData;
        },
        loading: 'Updating...',
        success: 'Marked as not required.',
        errorFallback: 'Failed to update.',
    },
];

function bindPostAction({ attr, confirm, buildBody, loading, success, errorFallback }) {
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest(`button[${attr}]`);
        if (!button) return;

        event.preventDefault();

        const result = await confirmDialog(confirm);
        if (!result.isConfirmed) return;

        const url = button.getAttribute(attr) ?? '';

        showLoading(loading);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: buildBody ? buildBody(result.value) : undefined,
            });

            closeAlert();

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message ?? errorFallback);
            }

            await successToast(success);
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'makeup-request-detail' }));
            dataTableInstance?.ajax.reload();
        } catch (error) {
            errorAlert(error instanceof Error ? error.message : 'An error occurred.');
        }
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    await initMakeupRequestsTable();
    bindStatusFilter();
    bindViewHandler();
    bindScheduleDetailsHandler();
    POST_ACTIONS.forEach(bindPostAction);
});
