import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from './sweetalert';
import { getBillingBadge } from './billing-status';

/**
 * Load and display schedule details in the modal.
 *
 * @param {number} scheduleId - The schedule ID to load
 * @param {string} detailsUrl - Base URL to fetch schedule details
 * @param {Object} [actionUrls] - Optional URL builders for action buttons
 */
export function openScheduleDetailsModal(scheduleId, detailsUrl, actionUrls = {}) {
    const $content = $('#scheduleDetailsContent');
    const $header = $('#scheduleDetailsHeader');
    const $footer = $('#scheduleDetailsFooter');

    if ($content.length) {
        $content.html(
            '<div class="text-center py-12"><p class="text-foreground/70">Loading schedule details...</p></div>'
        );
    }
    if ($footer.length) {
        $footer.addClass('hidden').empty();
    }

    // Reset header to default
    if ($header.length) {
        $header.find('h3').text('Schedule Details');
        $header.find('.schedule-header-badges').remove();
    }

    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'scheduleDetailsModal' }));

    $.ajax({
        url: `${detailsUrl}/${scheduleId}`,
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        success(response) {
            renderScheduleDetails(response.schedule, actionUrls);
        },
        error(xhr) {
            const msg = xhr.responseJSON?.message || 'Failed to load schedule details.';
            if ($content.length) {
                $content.html(
                    `<div class="text-center py-12"><p class="text-danger">${msg}</p></div>`
                );
            }
        },
    });
}

function renderScheduleDetails(schedule, actionUrls) {
    const $content = $('#scheduleDetailsContent');
    if (!$content.length) return;

    renderHeader(schedule);
    $content.html(buildDetailsHtml(schedule));
    renderFooter(schedule, actionUrls);
}

function renderHeader(schedule) {
    const $header = $('#scheduleDetailsHeader');
    if (!$header.length) return;

    // Remove old badges
    $header.find('.schedule-header-badges').remove();

    const studentName = schedule.student?.name || 'Unknown Student';
    const serviceName = schedule.service?.name || '';
    const title = studentName + (serviceName ? ` — ${serviceName}` : '');

    $header.find('h3').html(`
        <span>${title}</span>
        <div class="schedule-header-badges flex items-center gap-2 mt-1 flex-wrap">
            ${buildStatusBadge(schedule.status)}
            ${buildBillingBadge(schedule.billing_status)}
            ${buildSessionLogLink(schedule.session_log)}
        </div>
    `);
}

function buildSessionLogLink(sessionLog) {
    if (!sessionLog || !sessionLog.url) return '';

    const statusLabel = sessionLog.status_label || 'Session log';
    const statusValue = sessionLog.status || '';
    const colorMap = {
        draft: 'bg-foreground/10 text-foreground/70',
        sent_back: 'bg-danger/10 text-danger',
        submitted: 'bg-primary/10 text-primary',
        approved: 'bg-success/10 text-success',
        cancelled: 'bg-foreground/10 text-foreground/50',
    };
    const cls = colorMap[statusValue] || 'bg-foreground/10 text-foreground/70';

    return `<a href="${sessionLog.url}" target="_blank" rel="noopener"
        class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full ${cls} hover:opacity-80 transition-opacity">
        View Session log: ${statusLabel}
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M14 3h7v7m0-7L10 14m-3-7H5a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-2" />
        </svg>
    </a>`;
}

function buildStatusBadge(status) {
    const map = {
        scheduled: { label: 'Scheduled', cls: 'bg-primary/10 text-primary' },
        completed: { label: 'Completed', cls: 'bg-success/10 text-success' },
        cancelled: { label: 'Cancelled', cls: 'bg-foreground/10 text-foreground/70' },
    };
    const s = map[status] || { label: status || '-', cls: 'bg-foreground/10 text-foreground/70' };
    return `<span class="text-xs font-medium px-2 py-0.5 rounded-full ${s.cls}">${s.label}</span>`;
}

function buildBillingBadge(billingStatus) {
    return getBillingBadge(billingStatus);
}

function buildDetailsHtml(schedule) {
    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

    // SSA Details
    if (schedule.ssa) {
        html += `
            <div class="bg-background/subtle rounded-lg p-4">
                <h4 class="text-sm font-semibold text-foreground mb-3">SSA Basic Details</h4>
                <div class="space-y-2.5 text-sm">
                    <div><div class="text-foreground/70 mb-1">Service</div><div class="text-foreground font-medium">${schedule.ssa.service?.name || schedule.service?.name || '-'}</div></div>
                    <div><div class="text-foreground/70 mb-1">Date Range</div><div class="text-foreground font-medium">${schedule.ssa.start_date_formatted || '-'}</div><div class="text-foreground font-medium">${schedule.ssa.end_date_formatted || '-'}</div></div>
                    <div><div class="text-foreground/70 mb-1">Session Details</div><div class="text-foreground font-medium">Minutes: ${schedule.ssa.minutes_per_session || '-'} x ${schedule.ssa.sessions_per_frequency || '-'}</div><div class="text-foreground font-medium">Frequency: ${schedule.ssa.frequency ? schedule.ssa.frequency.replace('_', '-').replace(/\\b\\w/g, l => l.toUpperCase()) : '-'}</div></div>
                    <div><div class="text-foreground/70 mb-1">Hours & Status</div><div class="text-foreground font-medium">THO: ${schedule.ssa.tho_hours != null ? Number(schedule.ssa.tho_hours).toFixed(2) : '0'}</div><div class="text-foreground font-medium">Served: ${schedule.ssa.served_hours != null ? Number(schedule.ssa.served_hours).toFixed(2) : '0'}</div><div class="text-foreground font-medium">Status: ${schedule.ssa.status ? schedule.ssa.status.toUpperCase() : '-'}</div></div>
                </div>
            </div>
        `;
    }

    // Schedule Details
    html += `
        <div class="bg-background/subtle rounded-lg p-4">
            <h4 class="text-sm font-semibold text-foreground mb-3">Schedule Details</h4>
            <div class="space-y-2.5 text-sm">
                <div><div class="text-foreground/70 mb-1">Date</div><div class="text-foreground font-medium">${schedule.schedule_date_formatted || schedule.schedule_date || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">Time</div><div class="text-foreground font-medium">${schedule.start_time_formatted || schedule.start_time || '-'} - ${schedule.end_time_formatted || schedule.end_time || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">Duration</div><div class="text-foreground font-medium">${schedule.duration_formatted || (schedule.duration_minutes != null ? schedule.duration_minutes + 'm' : '-')}</div></div>
                <div><div class="text-foreground/70 mb-1">Service</div><div class="text-foreground font-medium">${schedule.service?.name || '-'}</div></div>
                ${schedule.therapist ? `<div><div class="text-foreground/70 mb-1">Therapist</div><div class="text-foreground font-medium">${schedule.therapist.name || '-'}</div></div>` : ''}
                ${schedule.location_details ? `<div class="pt-2.5"><div class="text-foreground/70 mb-1">Meeting Details</div><div class="text-foreground leading-relaxed whitespace-pre-wrap">${schedule.location_details}</div></div>` : ''}
                ${schedule.notes ? `<div class="pt-2.5"><div class="text-foreground/70 mb-1">Notes</div><div class="text-foreground leading-relaxed whitespace-pre-wrap">${schedule.notes}</div></div>` : ''}
            </div>
        </div>
    `;
    html += '</div>';

    // Student & Parent Details
    html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
    html += `
        <div class="bg-background/subtle rounded-lg p-4">
            <h4 class="text-sm font-semibold text-foreground mb-3">Student Details</h4>
            <div class="space-y-2.5 text-sm">
                <div><div class="text-foreground/70 mb-1">Name</div><div class="text-foreground font-medium">${schedule.student?.name || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">ID Number</div><div class="text-foreground font-medium">${schedule.student?.id_number || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">Email</div><div class="text-foreground font-medium">${schedule.student?.email || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">School/Family</div><div class="text-foreground font-medium">${schedule.school?.name || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">Timezone</div><div class="text-foreground font-medium">${schedule.student?.timezone || '-'}</div></div>
            </div>
        </div>
        <div class="bg-background/subtle rounded-lg p-4">
            <h4 class="text-sm font-semibold text-foreground mb-3">Parent Information</h4>
            <div class="space-y-2.5 text-sm">
                <div><div class="text-foreground/70 mb-1">Name</div><div class="text-foreground font-medium">${schedule.parent?.name || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">Email</div><div class="text-foreground font-medium">${schedule.parent?.email || '-'}</div></div>
                <div><div class="text-foreground/70 mb-1">Phone</div><div class="text-foreground font-medium">${schedule.parent?.phone || '-'}</div></div>
            </div>
        </div>
    `;
    html += '</div>';

    if (schedule.email_logs && schedule.email_logs.length > 0) {
        html += `
            <div class="mt-4">
                <h4 class="text-sm font-semibold text-foreground mb-3">Email History</h4>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-border">
                                <th class="text-left py-2 px-3 text-xs font-medium text-foreground/70">Date/Time</th>
                                <th class="text-left py-2 px-3 text-xs font-medium text-foreground/70">Type</th>
                                <th class="text-left py-2 px-3 text-xs font-medium text-foreground/70">Recipient</th>
                                <th class="text-left py-2 px-3 text-xs font-medium text-foreground/70">Sent By</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${schedule.email_logs.map(log => `
                                <tr class="border-b border-border last:border-0">
                                    <td class="py-2 px-3">${log.sent_at}</td>
                                    <td class="py-2 px-3">
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full ${
                                            ['notification_created', 'notification_updated'].includes(log.type_value)
                                                ? 'bg-primary/10 text-primary'
                                                : 'bg-foreground/10 text-foreground/70'
                                        }">${log.type_label}</span>
                                    </td>
                                    <td class="py-2 px-3">${log.recipient_email}</td>
                                    <td class="py-2 px-3">${log.sent_by}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    return html;
}

function renderFooter(schedule, actionUrls) {
    const $footer = $('#scheduleDetailsFooter');
    if (!$footer.length) return;

    const isPast = Boolean(schedule.is_past ?? false);
    const isBilled = schedule.billing_status === 'billed';
    const isPendingBilling = schedule.billing_status === 'pending';
    const isCancelled = schedule.status === 'cancelled';
    const hasAnyAction = actionUrls.editUrl || actionUrls.billUrl || actionUrls.sessionLogUrl;

    let buttons = '';

    // Edit button: visible when not billed AND not cancelled AND URL provided
    if (!isBilled && !isCancelled && actionUrls.editUrl) {
        buttons += `<a href="${actionUrls.editUrl(schedule.id)}" class="inline-flex items-center px-4 py-2 border border-border rounded-lg hover:bg-background/subtle text-sm font-medium text-foreground transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            Edit
        </a>`;
    }

    // Delete button: visible when not billed AND has any action URL (therapist mode)
    if (!isBilled && hasAnyAction) {
        buttons += `<button type="button" class="schedule-delete-btn inline-flex items-center px-4 py-2 border border-danger/30 text-danger rounded-lg hover:bg-danger/10 text-sm font-medium transition-colors" data-schedule-id="${schedule.id}">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Delete
        </button>`;
    }

    // Delete Future Schedules button: visible when recurring AND not billed AND has actions
    if (!isBilled && hasAnyAction && schedule.is_recurring) {
        buttons += `<button type="button" class="schedule-delete-future-btn inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium transition-colors" data-schedule-id="${schedule.id}">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Delete Future Schedules
        </button>`;
    }

    // Bill Session button: past + pending billing + URL provided
    if (isPast && isPendingBilling && !isCancelled && actionUrls.billUrl) {
        buttons += `<a href="${actionUrls.billUrl(schedule.id)}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            Bill Session
        </a>`;
    }

    // View Session Log button: past + billed + URL provided
    if (isPast && isBilled && actionUrls.sessionLogUrl) {
        const url = actionUrls.sessionLogUrl(schedule.id);
        if (url) {
            buttons += `<a href="${url}" class="inline-flex items-center px-4 py-2 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 text-sm font-medium transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                View Session Log
            </a>`;
        }
    }

    if (!buttons) {
        $footer.addClass('hidden').empty();
        return;
    }

    $footer.removeClass('hidden').html(
        `<div class="flex items-center justify-end gap-2">${buttons}</div>`
    );
}

/**
 * Bind the delete handler for schedule-delete-btn buttons inside modals.
 *
 * @param {string} deleteUrl - Base URL for DELETE request (e.g., '/therapist/schedule')
 * @param {Function} onSuccess - Callback after successful deletion
 */
export function bindDeleteHandler(deleteUrl, onSuccess) {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.schedule-delete-btn');
        if (!btn) return;

        const scheduleId = btn.dataset.scheduleId;
        if (!scheduleId) return;

        const result = await confirmDialog({
            title: 'Delete Schedule?',
            text: 'This will remove the schedule. This action cannot be undone.',
            icon: 'warning',
            confirmButtonText: 'Yes, delete it',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
        });

        if (!result.isConfirmed) return;

        showLoading('Deleting schedule...');
        try {
            const response = await fetch(`${deleteUrl}/${scheduleId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            closeAlert();
            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.message || 'Failed to delete schedule');
            }
            successToast('Schedule deleted successfully.');
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'scheduleDetailsModal' }));
            if (typeof onSuccess === 'function') onSuccess();
        } catch (error) {
            errorAlert(error.message || 'An error occurred while deleting the schedule');
        }
    });
}

/**
 * Bind the delete-future handler for recurring schedule deletion.
 *
 * @param {string} deleteUrl - Base URL for DELETE request (e.g., '/therapist/schedule')
 * @param {Function} onSuccess - Callback after successful deletion
 */
export function bindDeleteFutureHandler(deleteUrl, onSuccess) {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.schedule-delete-future-btn');
        if (!btn) return;

        const scheduleId = btn.dataset.scheduleId;
        if (!scheduleId) return;

        const result = await confirmDialog({
            title: 'Delete Future Recurring Schedules?',
            text: 'This will permanently delete this schedule and all future recurring schedules in this series. Past schedules will not be affected. This action cannot be undone.',
            icon: 'warning',
            confirmButtonText: 'Yes, delete future schedules',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
        });

        if (!result.isConfirmed) return;

        showLoading('Deleting future schedules...');
        try {
            const response = await fetch(`${deleteUrl}/${scheduleId}/future-recurring`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            closeAlert();
            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.message || 'Failed to delete future schedules');
            }
            const data = await response.json();
            successToast(`${data.deleted_count} future schedule(s) deleted successfully.`);
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'scheduleDetailsModal' }));
            if (typeof onSuccess === 'function') onSuccess();
        } catch (error) {
            errorAlert(error.message || 'An error occurred while deleting future schedules');
        }
    });
}
