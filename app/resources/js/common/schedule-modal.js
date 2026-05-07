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
    const $headerActions = $('#scheduleDetailsHeaderActions');

    if ($content.length) {
        $content.html(
            '<div class="text-center py-12"><p class="text-foreground/70">Loading schedule details...</p></div>'
        );
    }
    if ($footer.length) {
        $footer.addClass('hidden').empty();
    }
    if ($headerActions.length) {
        $headerActions.empty();
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
    const $headerActions = $('#scheduleDetailsHeaderActions');
    if (!$content.length) return;

    if ($headerActions.length) {
        $headerActions.html(buildSessionLogLink(schedule.session_log));
    }

    $content.html(`
        <div class="flex flex-col lg:flex-row min-h-full">
            <aside class="lg:w-80 shrink-0 border-b lg:border-b-0 lg:border-r border-border p-6 bg-muted/20">
                ${buildLeftSidebar(schedule)}
            </aside>
            <div class="flex-1 min-w-0 p-6 space-y-6">
                ${buildSessionStrip(schedule)}
                ${schedule.ssa ? buildSsaCard(schedule.ssa) : ''}
                ${buildEmailHistory(schedule)}
            </div>
        </div>
    `);

    renderFooter(schedule, actionUrls);
}

function buildJoinSessionButton(schedule) {
    const link = schedule.meeting_link;
    if (!link) return '';
    if (schedule.is_past) return '';
    if (schedule.status === 'cancelled') return '';

    return `<a href="${escapeAttr(link)}" target="_blank" rel="noopener"
        class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg bg-primary text-white shadow-sm hover:bg-success/90 hover:shadow transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
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
/* Left sidebar: student summary + therapist + parent/guardian                */
/* -------------------------------------------------------------------------- */

function buildLeftSidebar(schedule) {
    const student = schedule.student || {};
    const school = schedule.school || {};
    const therapist = schedule.therapist || {};
    const parent = schedule.parent || {};

    const idRaw = student.id_number && student.id_number !== '-'
        ? String(student.id_number).replace(/^#+/, '').trim()
        : '';
    const idPart = idRaw ? `ID #${escapeHtml(idRaw)}` : '';
    const tzPart = student.timezone_label && student.timezone_label !== '-'
        ? escapeHtml(student.timezone_label)
        : '';
    const studentMeta = [idPart, tzPart].filter(Boolean).join(' · ');

    const schoolName = school.name && school.name !== '-' ? school.name : '';

    const therapistItems = [
        { label: 'Assigned', value: therapist.name || '-', icon: userIcon('w-4 h-4') },
        { label: 'Timezone', value: schedule.timezone_label || schedule.timezone || '-', icon: clockIcon('w-4 h-4') },
    ];

    return `
        <div class="space-y-5">
            <div class="flex items-center gap-2 flex-wrap">
                ${statusBadgeWithDot(schedule.status)}
                ${billingBadgeWithDot(schedule.billing_status)}
            </div>

            <div>
                <h2 class="text-xl font-semibold text-foreground leading-tight break-words">${escapeHtml(student.name || 'Unknown Student')}</h2>
                ${studentMeta ? `<p class="mt-1 text-xs text-foreground/60">${studentMeta}</p>` : ''}
            </div>

            ${schoolName ? buildSchoolChip(schoolName) : ''}

            <div class="border-t border-border"></div>

            ${buildSidebarSection('Therapist', therapistItems)}

            <div class="border-t border-border"></div>

            ${buildParentSection(parent)}
        </div>
    `;
}

function buildSidebarSection(title, items) {
    return `
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground/60 mb-3">${escapeHtml(title)}</h3>
            <div class="space-y-3">
                ${items.map(buildSidebarRow).join('')}
            </div>
        </div>
    `;
}

function buildSidebarRow(item) {
    const safeValue = item.isHtml
        ? (item.value || '-')
        : escapeHtml(item.value || '-');
    return `
        <div class="flex items-start gap-3">
            <div class="shrink-0 w-8 h-8 rounded-md bg-background border border-border flex items-center justify-center text-foreground/60" aria-hidden="true">
                ${item.icon}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-foreground/50">${escapeHtml(item.label)}</p>
                <p class="mt-0.5 text-sm font-semibold text-foreground break-words">${safeValue}</p>
            </div>
        </div>
    `;
}

function buildParentSection(parent) {
    const name = parent.name && parent.name !== '-' ? parent.name : 'Not provided';
    const email = parent.email && parent.email !== '-' ? parent.email : null;
    const phone = parent.phone && parent.phone !== '-' ? parent.phone : null;

    const items = [
        { label: 'Primary Contact', value: name, icon: peopleIcon('w-4 h-4') },
    ];
    if (email) {
        items.push({ label: 'Email', value: emailLink(email), icon: mailIcon('w-4 h-4'), isHtml: true });
    }
    if (phone) {
        items.push({ label: 'Phone', value: phone, icon: phoneIcon('w-4 h-4') });
    }

    return buildSidebarSection('Parent / Guardian', items);
}

function buildSchoolChip(name) {
    return `
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-background border border-border text-xs font-medium text-foreground/80">
            <span class="text-foreground/50" aria-hidden="true">${schoolIcon('w-3.5 h-3.5')}</span>
            ${escapeHtml(name)}
        </span>
    `;
}

/* -------------------------------------------------------------------------- */
/* Session-time strip (right panel)                                            */
/* -------------------------------------------------------------------------- */

function buildSessionStrip(schedule) {
    const dateParts = parseDateParts(schedule.schedule_date_formatted, schedule.schedule_date);
    const start = schedule.start_time_formatted || '';
    const end = schedule.end_time_formatted || '';
    const timeRange = start && end ? `${escapeHtml(start)} – ${escapeHtml(end)}` : (escapeHtml(start || end) || '-');
    const duration = schedule.duration_formatted || '';
    const tzLabel = schedule.timezone_label || schedule.timezone || '';
    const meta = [duration ? `${escapeHtml(duration)} duration` : '', tzLabel ? escapeHtml(tzLabel) : '']
        .filter(Boolean)
        .join(' · ');
    const serviceName = schedule.service?.name || '';

    const dateTile = `
        <div class="shrink-0 w-16 rounded-lg border border-border bg-background overflow-hidden text-center">
            <div class="bg-danger/10 text-[10px] font-bold uppercase tracking-wider text-danger py-0.5">${escapeHtml(dateParts.month)}</div>
            <div class="text-2xl font-semibold text-foreground leading-tight pt-1">${escapeHtml(dateParts.day)}</div>
            <div class="text-[10px] font-medium uppercase tracking-wider text-foreground/50 pb-1">${escapeHtml(dateParts.weekday)}</div>
        </div>
    `;

    const sectionLabel = serviceName
        ? `<span class="w-1.5 h-1.5 rounded-full bg-primary" aria-hidden="true"></span>${escapeHtml(serviceName)}`
        : 'Session Time';
    const joinButton = buildJoinSessionButton(schedule);

    return `
        <section class="flex items-center gap-4 rounded-xl bg-muted/40 border border-border p-4">
            ${dateTile}
            <div class="min-w-0 flex-1">
                <p class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-foreground/60">${sectionLabel}</p>
                <p class="mt-1 text-lg font-semibold text-foreground leading-tight">${timeRange}</p>
                ${meta ? `<p class="mt-1 text-xs text-foreground/60">${meta}</p>` : ''}
            </div>
            ${joinButton ? `<div class="shrink-0">${joinButton}</div>` : ''}
        </section>
    `;
}

function parseDateParts(formatted, iso) {
    // formatted looks like "May 07, 2026"; iso like "2026-05-07"
    let month = '';
    let day = '';
    let weekday = '';

    if (formatted) {
        const m = String(formatted).match(/^([A-Za-z]+)\s+(\d{1,2})/);
        if (m) {
            month = m[1].toUpperCase();
            day = m[2].padStart(2, '0');
        }
    }
    if (iso) {
        const d = new Date(`${iso}T00:00:00`);
        if (!Number.isNaN(d.getTime())) {
            weekday = d.toLocaleDateString('en-US', { weekday: 'short' }).toUpperCase();
            if (!month) month = d.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
            if (!day) day = String(d.getDate()).padStart(2, '0');
        }
    }
    return { month, day, weekday };
}

function buildSsaCard(ssa) {
    const dateRange = ssa.date_range_formatted || '';
    const freq = ssa.frequency ? capitalize(String(ssa.frequency).replace(/_/g, ' ')) : '-';
    const freqSub = formatFrequencySub(ssa);
    const minutes = ssa.minutes_per_session;
    const perSession = minutes ? `${minutes} min` : '-';
    const perSessionSub = minutes ? formatHourBlock(minutes) : '';
    const tho = Number(ssa.tho_hours) || 0;
    const served = Number(ssa.served_hours) || 0;
    const authorized = tho > 0 ? `${formatHoursLong(tho)}` : '-';
    const pct = tho > 0 ? Math.min(100, Math.round((served / tho) * 100)) : 0;
    const remaining = Math.max(0, tho - served);
    const barColor = pct >= 100 ? 'bg-success' : pct >= 80 ? 'bg-warning' : 'bg-primary';

    const dateRangePill = dateRange
        ? `<span class="inline-flex items-center px-3 py-1 rounded-full bg-muted text-xs font-medium text-foreground/70">${escapeHtml(dateRange)}</span>`
        : '';

    return `
        <section class="rounded-xl border border-border bg-background p-5">
            <header class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="shrink-0 text-foreground/40" aria-hidden="true">${docIcon('w-4 h-4')}</span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-foreground/70">SSA Details</h3>
                </div>
                ${dateRangePill}
            </header>

            <div class="grid grid-cols-4 rounded-lg border border-border overflow-hidden">
                ${ssaMetricCell('Frequency', escapeHtml(freq), freqSub)}
                ${ssaMetricCell('Per Session', escapeHtml(perSession), perSessionSub)}
                ${ssaMetricCell('Authorized', escapeHtml(authorized), 'Total hours')}
                ${ssaUsageCell(served, tho, pct, remaining, barColor)}
            </div>
        </section>
    `;
}

function ssaMetricCell(label, value, sub) {
    return `
        <div class="p-4 border-r border-border last:border-r-0">
            <p class="text-[10px] font-bold uppercase tracking-wider text-foreground/50">${escapeHtml(label)}</p>
            <p class="mt-1 text-lg font-semibold text-foreground leading-tight">${value}</p>
            ${sub ? `<p class="mt-0.5 text-xs text-foreground/60">${escapeHtml(sub)}</p>` : ''}
        </div>
    `;
}

function ssaUsageCell(served, tho, pct, remaining, barColor) {
    if (tho <= 0) {
        return ssaMetricCell('Usage', '-', '');
    }
    return `
        <div class="p-4 bg-primary/5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-foreground/50">Usage</p>
            <p class="mt-1 text-lg font-semibold text-foreground leading-tight">
                ${formatHoursShort(served)} <span class="text-foreground/40 font-normal text-sm">of ${formatHoursShort(tho)}</span>
            </p>
            <div class="mt-2 h-1.5 w-full rounded-full bg-muted overflow-hidden" role="progressbar" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full ${barColor} transition-all" style="width: ${pct}%"></div>
            </div>
            <div class="mt-1.5 flex items-center justify-between text-xs text-foreground/60">
                <span>${pct}% used</span>
                <span>${formatHoursShort(remaining)} left</span>
            </div>
        </div>
    `;
}

function formatFrequencySub(ssa) {
    const sessions = ssa.sessions_per_frequency;
    const freq = ssa.frequency;
    if (!sessions || !freq) return '';
    const freqLabel = { weekly: 'week', monthly: 'month', daily: 'day' }[freq] || freq;
    const word = sessions === 1 ? 'Once' : `${sessions} times`;
    return `${word} per ${freqLabel}`;
}

function formatHourBlock(minutes) {
    const m = Number(minutes) || 0;
    if (m <= 0) return '';
    const hours = m / 60;
    if (hours >= 1 && Number.isInteger(hours)) {
        return hours === 1 ? '1 hour block' : `${hours} hour block`;
    }
    return `${m} min block`;
}

function formatHoursLong(value) {
    const n = Number(value) || 0;
    const display = n % 1 === 0 ? n : n.toFixed(1);
    return n === 1 ? '1 hour' : `${display} hours`;
}

function formatHoursShort(value) {
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

function emailLink(email) {
    if (!email || email === '-') return '-';
    return `<a href="mailto:${escapeAttr(email)}" class="text-primary hover:underline break-all">${escapeHtml(email)}</a>`;
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/* Icons */
function clockIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
}
function mailIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>`;
}
function phoneIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>`;
}
function schoolIcon(cls) {
    return `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 9h.01"/><path d="M9 12h.01"/><path d="M9 15h.01"/><path d="M9 18h.01"/></svg>`;
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
