import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from './sweetalert';
import { getBillingLabel, BILLING_STATUSES } from './billing-status';

/**
 * Load and display schedule details in the modal.
 *
 * @param {number} scheduleId - The schedule ID to load
 * @param {string} detailsUrl - Base URL to fetch schedule details
 * @param {Object} [actionUrls] - Optional URL builders for action buttons
 */
export function openScheduleDetailsModal(scheduleId, detailsUrl, actionUrls = {}) {
    const $content = $('#scheduleDetailsContent');
    const $footer = $('#scheduleDetailsFooter');
    const $headerInner = $('#scheduleDetailsHeaderInner');

    if ($content.length) {
        $content.html(
            '<div class="text-center py-12"><p class="text-foreground/70">Loading schedule details...</p></div>'
        );
    }
    if ($footer.length) {
        $footer.addClass('hidden').empty();
    }
    if ($headerInner.length) {
        $headerInner.html('<h3 class="text-lg font-semibold text-foreground">Schedule Details</h3>');
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
    const $headerInner = $('#scheduleDetailsHeaderInner');
    if (!$content.length) return;

    if ($headerInner.length) {
        $headerInner.html(buildHeader(schedule));
    }

    $content.html([
        buildBody(schedule),
        buildEmailHistory(schedule),
    ].join(''));

    renderFooter(schedule, actionUrls);
}

/* -------------------------------------------------------------------------- */
/* Header (rendered into the modal's top bar)                                  */
/* -------------------------------------------------------------------------- */

function buildHeader(schedule) {
    const studentName = schedule.student?.name || 'Unknown Student';
    const serviceName = schedule.service?.name || '';
    const dateLine = formatDateLine(schedule);

    return `
        <div class="flex items-center gap-4 min-w-0 w-full">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                    ${calendarIcon('w-5 h-5')}
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap min-w-0">
                        <h2 class="text-lg font-semibold text-foreground leading-tight truncate">${escapeHtml(studentName)}</h2>
                        ${statusBadgeWithDot(schedule.status)}
                        ${billingBadgeWithDot(schedule.billing_status)}
                        ${buildSessionLogLink(schedule.session_log)}
                    </div>
                    <p class="mt-0.5 text-sm text-foreground/60 truncate">
                        ${serviceName ? escapeHtml(serviceName) : ''}${serviceName && dateLine ? ' · ' : ''}${dateLine}
                    </p>
                </div>
            </div>
            <div class="shrink-0">
                ${buildJoinSessionButton(schedule)}
            </div>
        </div>
    `;
}

function formatDateLine(schedule) {
    const date = schedule.schedule_date_formatted || '';
    const start = schedule.start_time_formatted || '';
    const end = schedule.end_time_formatted || '';
    if (!date) return '';
    if (start && end) return `${escapeHtml(date)}, ${escapeHtml(start)} – ${escapeHtml(end)}`;
    return escapeHtml(date);
}

function buildJoinSessionButton(schedule) {
    const link = schedule.meeting_link;
    if (!link) return '';
    if (schedule.is_past) return '';
    if (schedule.status === 'cancelled') return '';

    return `<a href="${escapeAttr(link)}" target="_blank" rel="noopener"
        class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg bg-success text-white shadow-sm hover:bg-success/90 hover:shadow transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        ${videoIcon('w-4 h-4')}
        Join Session
    </a>`;
}

function buildSessionLogLink(sessionLog) {
    if (!sessionLog || !sessionLog.url) return '';

    const statusLabel = sessionLog.status_label || 'Session log';
    const statusValue = sessionLog.status || '';
    const colorMap = {
        draft: { bg: 'bg-foreground/10', text: 'text-foreground/70' },
        sent_back: { bg: 'bg-danger/10', text: 'text-danger' },
        submitted: { bg: 'bg-primary/10', text: 'text-primary' },
        approved: { bg: 'bg-success/10', text: 'text-success' },
        cancelled: { bg: 'bg-foreground/10', text: 'text-foreground/50' },
    };
    const c = colorMap[statusValue] || { bg: 'bg-primary/10', text: 'text-primary' };

    return `<a href="${sessionLog.url}" target="_blank" rel="noopener"
        class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full ${c.bg} ${c.text} hover:opacity-80 transition-opacity focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        View Session Log: ${escapeHtml(statusLabel)}
        ${externalIcon('w-3 h-3')}
    </a>`;
}

/* -------------------------------------------------------------------------- */
/* Status indicators row                                                       */
/* -------------------------------------------------------------------------- */

function statusBadgeWithDot(status) {
    const map = {
        scheduled: { label: 'Scheduled', dot: 'bg-primary', text: 'text-primary', bg: 'bg-primary/10' },
        completed: { label: 'Completed', dot: 'bg-success', text: 'text-success', bg: 'bg-success/10' },
        cancelled: { label: 'Cancelled', dot: 'bg-foreground/40', text: 'text-foreground/70', bg: 'bg-foreground/10' },
    };
    const s = map[status] || { label: status || '-', dot: 'bg-foreground/40', text: 'text-foreground/70', bg: 'bg-foreground/10' };
    return badgePill(s.label, s.dot, s.text, s.bg);
}

function billingBadgeWithDot(billingStatus) {
    const meta = BILLING_STATUSES[billingStatus];
    if (!meta) {
        return badgePill(getBillingLabel(billingStatus), 'bg-foreground/40', 'text-foreground/70', 'bg-foreground/10');
    }
    const dotMap = {
        pending: 'bg-warning',
        billed: 'bg-success',
        not_billable: 'bg-foreground/40',
    };
    const textMap = {
        pending: 'text-warning',
        billed: 'text-success',
        not_billable: 'text-foreground/70',
    };
    const bgMap = {
        pending: 'bg-warning/10',
        billed: 'bg-success/10',
        not_billable: 'bg-foreground/10',
    };
    return badgePill(meta.label, dotMap[billingStatus] || 'bg-foreground/40', textMap[billingStatus] || 'text-foreground/70', bgMap[billingStatus] || 'bg-foreground/10');
}

function badgePill(label, dotCls, textCls, bgCls) {
    return `<span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full ${bgCls} ${textCls}">
        <span class="w-1.5 h-1.5 rounded-full ${dotCls}" aria-hidden="true"></span>
        ${escapeHtml(label)}
    </span>`;
}

/* -------------------------------------------------------------------------- */
/* Body: 2-column card grid                                                    */
/* -------------------------------------------------------------------------- */

function buildBody(schedule) {
    return `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            ${cardSection({
                title: 'Service & Scheduled Session',
                iconBgCls: 'bg-primary/10 text-primary',
                icon: briefcaseIcon('w-5 h-5'),
                emphasized: true,
                body: buildServiceFields(schedule),
            })}

            ${cardSection({
                title: 'Student Details',
                iconBgCls: 'bg-primary/10 text-primary',
                icon: userIcon('w-5 h-5'),
                body: buildStudentFields(schedule),
            })}

            ${schedule.ssa ? cardSection({
                title: 'SSA Details',
                iconBgCls: 'bg-primary/10 text-primary',
                icon: docIcon('w-5 h-5'),
                body: buildSsaFields(schedule.ssa),
            }) : ''}

            ${cardSection({
                title: 'Parent Information',
                iconBgCls: 'bg-success/10 text-success',
                icon: peopleIcon('w-5 h-5'),
                body: buildParentBody(schedule),
            })}
        </div>
    `;
}

function cardSection({ title, icon, iconBgCls, body, emphasized = false }) {
    const wrapCls = [
        'rounded-xl border p-5',
        emphasized ? 'bg-muted/40 border-border' : 'bg-background border-border',
    ].join(' ');

    return `
        <section class="${wrapCls}">
            <header class="flex items-center gap-3 mb-5">
                <div class="shrink-0 w-9 h-9 rounded-lg ${iconBgCls} flex items-center justify-center" aria-hidden="true">
                    ${icon}
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">${escapeHtml(title)}</h3>
            </header>
            ${body}
        </section>
    `;
}

function buildServiceFields(schedule) {
    const therapistName = schedule.therapist?.name || '-';
    return fieldGrid([
        ['Service', schedule.service?.name || '-'],
        ['Therapist', `<span class="text-primary">${escapeHtml(therapistName)}</span>`],
        ['Date', schedule.schedule_date_formatted || '-'],
        ['Time', `${escapeHtml(schedule.start_time_formatted || '')} - ${escapeHtml(schedule.end_time_formatted || '')}`.trim() || '-'],
        ['Duration', schedule.duration_formatted || '-'],
        ['Timezone', schedule.timezone_label || schedule.timezone || '-'],
    ]);
}

function buildStudentFields(schedule) {
    const student = schedule.student || {};
    const school = schedule.school || {};
    return fieldGrid([
        ['Student Name', student.name || '-'],
        ['ID Number', student.id_number || '-'],
        ['Email Address', emailLink(student.email)],
        [null, null],
        ['School / Family', school.name || '-'],
        ['Timezone', student.timezone_label || student.timezone || '-'],
    ]);
}

function buildParentBody(schedule) {
    const parent = schedule.parent || {};
    const initials = parentInitials(parent.name);
    const name = parent.name && parent.name !== '-' ? parent.name : 'Not provided';
    const email = parent.email && parent.email !== '-' ? parent.email : null;
    const phone = parent.phone && parent.phone !== '-' ? parent.phone : null;

    return `
        <div class="flex items-start gap-3">
            <div class="shrink-0 w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-semibold" aria-hidden="true">
                ${escapeHtml(initials)}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-foreground/60">Primary Contact</p>
                <p class="text-sm font-semibold text-foreground mt-0.5">${escapeHtml(name)}</p>
                ${email ? `<p class="text-sm text-foreground/70 mt-2 break-all">${emailLink(email)}</p>` : ''}
                <p class="text-sm text-foreground/70 mt-1">${phone ? escapeHtml(phone) : '-'}</p>
            </div>
        </div>
    `;
}

function buildSsaFields(ssa) {
    const sessionDetails = formatSessionDetails(ssa);
    const fields = fieldGrid([
        ['Date Range', ssa.date_range_formatted || '-'],
        ['Frequency', ssa.frequency ? capitalize(ssa.frequency.replace(/_/g, ' ')) : '-'],
        ['Session Details', sessionDetails],
    ]);

    return `
        ${fields}
        ${buildHoursProgress(ssa)}
    `;
}

function formatSessionDetails(ssa) {
    const minutes = ssa.minutes_per_session;
    const sessions = ssa.sessions_per_frequency;
    const freq = ssa.frequency;
    if (!minutes || !sessions || !freq) {
        return ssa.summary_line || '-';
    }
    const freqLabel = { weekly: 'week', monthly: 'month', daily: 'day' }[freq] || freq;
    const sessionWord = sessions === 1 ? 'session' : 'sessions';
    return `${minutes} min · ${sessions} ${sessionWord} per ${freqLabel}`;
}

function buildHoursProgress(ssa) {
    const tho = Number(ssa.tho_hours) || 0;
    const served = Number(ssa.served_hours) || 0;

    if (tho <= 0 || served <= 0) {
        return `
            <div class="mt-4 pt-4 border-t border-border">
                <dt class="text-xs text-foreground/60">Hours Used</dt>
                <dd class="mt-1 text-sm font-semibold text-foreground">${formatHours(served)} of ${formatHours(tho)}</dd>
            </div>
        `;
    }

    const pct = Math.min(100, Math.round((served / tho) * 100));
    const remaining = Math.max(0, tho - served);
    const barColor = pct >= 100 ? 'bg-success' : pct >= 80 ? 'bg-warning' : 'bg-primary';

    return `
        <div class="mt-4 pt-4 border-t border-border">
            <div class="flex items-baseline justify-between gap-3">
                <dt class="text-xs text-foreground/60">Hours Used</dt>
                <dd class="text-xs font-semibold text-foreground">
                    ${formatHours(served)} <span class="text-foreground/50 font-normal">of ${formatHours(tho)} · ${pct}%</span>
                </dd>
            </div>
            <div class="mt-1.5 h-2 w-full rounded-full bg-muted overflow-hidden" role="progressbar" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full ${barColor} transition-all" style="width: ${pct}%"></div>
            </div>
            <div class="mt-1.5 flex items-center justify-between text-xs text-foreground/60">
                <span>${formatHours(remaining)} remaining</span>
                <span>Total ${formatHours(tho)}</span>
            </div>
        </div>
    `;
}

function formatHours(value) {
    const n = Number(value) || 0;
    return `${n % 1 === 0 ? n : n.toFixed(1)}h`;
}

/* -------------------------------------------------------------------------- */
/* Email history (full-width, collapsible)                                    */
/* -------------------------------------------------------------------------- */

function buildEmailHistory(schedule) {
    const logs = schedule.email_logs || [];
    if (!logs.length) return '';

    const rows = logs.map(log => `
        <tr class="border-b border-border last:border-0">
            <td class="py-3 px-4 text-sm text-foreground">${escapeHtml(log.sent_at || '')}</td>
            <td class="py-3 px-4">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full ${
                    ['notification_created', 'notification_updated'].includes(log.type_value)
                        ? 'bg-primary/10 text-primary'
                        : 'bg-foreground/10 text-foreground/70'
                }">${escapeHtml(log.type_label || '')}</span>
            </td>
            <td class="py-3 px-4 text-sm text-foreground/80 break-all">${escapeHtml(log.recipient_email || '')}</td>
            <td class="py-3 px-4 text-sm text-foreground/80">${escapeHtml(log.sent_by || '')}</td>
        </tr>
    `).join('');

    return `
        <div class="mt-8">
        <details class="rounded-xl border border-border bg-background overflow-hidden group" open>
            <summary class="cursor-pointer list-none flex items-center justify-between px-5 py-4 bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <span class="text-xs font-bold uppercase tracking-wider text-foreground">
                    Email History
                </span>
                <span class="text-xs text-foreground/60">${logs.length} email${logs.length === 1 ? '' : 's'} sent</span>
            </summary>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-background">
                            <th class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-foreground/60">Date / Time</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-foreground/60">Type</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-foreground/60">Recipient</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-foreground/60">Sent By</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </details>
        </div>
    `;
}

/* -------------------------------------------------------------------------- */
/* Footer                                                                      */
/* -------------------------------------------------------------------------- */

function renderFooter(schedule, actionUrls) {
    const $footer = $('#scheduleDetailsFooter');
    if (!$footer.length) return;

    const isPast = Boolean(schedule.is_past ?? false);
    const isBilled = schedule.billing_status === 'billed';
    const isPendingBilling = schedule.billing_status === 'pending';
    const isCancelled = schedule.status === 'cancelled';
    const hasAnyAction = actionUrls.editUrl || actionUrls.billUrl || actionUrls.sessionLogUrl;

    const buttons = [];

    if (!isBilled && hasAnyAction) {
        buttons.push(`<button type="button" class="schedule-delete-btn inline-flex items-center px-4 py-2 border border-danger/30 text-danger rounded-lg hover:bg-danger/10 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" data-schedule-id="${schedule.id}">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Delete
        </button>`);
    }

    if (!isBilled && hasAnyAction && schedule.is_recurring) {
        buttons.push(`<button type="button" class="schedule-delete-future-btn inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" data-schedule-id="${schedule.id}">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Delete Future Schedules
        </button>`);
    }

    if (!isBilled && !isCancelled && actionUrls.editUrl) {
        buttons.push(`<a href="${actionUrls.editUrl(schedule.id)}" class="inline-flex items-center px-4 py-2 border border-border rounded-lg hover:bg-muted/50 text-sm font-medium text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            Edit Schedule
        </a>`);
    }

    if (isPast && isPendingBilling && !isCancelled && actionUrls.billUrl) {
        buttons.push(`<a href="${actionUrls.billUrl(schedule.id)}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            Bill Session
        </a>`);
    }

    if (isPast && isBilled && actionUrls.sessionLogUrl) {
        const url = actionUrls.sessionLogUrl(schedule.id);
        if (url) {
            buttons.push(`<a href="${url}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                View Session Log
            </a>`);
        }
    }

    if (!buttons.length) {
        $footer.addClass('hidden').empty();
        return;
    }

    $footer.removeClass('hidden').html(
        `<div class="flex items-center justify-end gap-2 flex-wrap">${buttons.join('')}</div>`
    );
}

/* -------------------------------------------------------------------------- */
/* Small helpers                                                               */
/* -------------------------------------------------------------------------- */

function fieldGrid(fields) {
    const cells = fields.map(([label, value]) => {
        if (label === null) {
            return `<div class="hidden md:block" aria-hidden="true"></div>`;
        }
        const safeValue = typeof value === 'string' && value.trimStart().startsWith('<')
            ? value
            : escapeHtml(value || '-');
        return `
            <div class="min-w-0">
                <dt class="text-xs text-foreground/60">${escapeHtml(label)}</dt>
                <dd class="mt-1 text-sm font-semibold text-foreground break-words">${safeValue}</dd>
            </div>
        `;
    }).join('');

    return `<dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">${cells}</dl>`;
}

function emailLink(email) {
    if (!email || email === '-') return '-';
    return `<a href="mailto:${escapeAttr(email)}" class="text-primary hover:underline break-all">${escapeHtml(email)}</a>`;
}

function parentInitials(name) {
    if (!name || name === '-') return 'PP';
    const parts = String(name).trim().split(/\s+/).slice(0, 2);
    return parts.map(p => p.charAt(0).toUpperCase()).join('') || 'PP';
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/* Icons */
function calendarIcon(cls) {
    return `<svg class="${cls} shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="16" y1="3" x2="16" y2="7"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="3" y1="11" x2="21" y2="11"/></svg>`;
}
function briefcaseIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>`;
}
function userIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;
}
function peopleIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`;
}
function docIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`;
}
function externalIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>`;
}
function videoIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>`;
}
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeAttr(value) {
    return escapeHtml(value);
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
