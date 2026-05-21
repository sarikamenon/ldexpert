import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

let dataTableInstance = null;

async function initMakeupRequestsTable() {
    const table = document.getElementById('makeupRequestsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    await loadDataTablesLibrary();

    dataTableInstance = await initServerSideDataTable('#makeupRequestsTable', dataUrl, {
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: [1, 2, 3, 4, 5] }],
        getExtraData(d) {
            const filter = document.getElementById('makeup-status-filter');
            d.filter_status = filter ? filter.value : '';
        },
    });
}

function bindStatusFilter() {
    const form = document.getElementById('makeup-filter-form');
    if (!form) return;

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (dataTableInstance) {
            dataTableInstance.ajax.reload();
        }
    });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}

const ICONS = {
    calendar: `<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
    calendarSolid: `<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
    mail: `<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
    warning: `<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v3m0 3.5h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>`,
    info: `<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
    userCheck: `<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 11a4 4 0 10-8 0 4 4 0 008 0zM3 21v-1a6 6 0 016-6h4m4 5l2 2 4-4"/></svg>`,
    xCircle: `<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10 14l4-4m0 4l-4-4m11 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
    clockSlash: `<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4m0 0l2 2m-9-2a9 9 0 1115.5 6.2M3 21l18-18"/></svg>`,
    arrow: `<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>`,
};

const PILL_STYLES = {
    primary: 'bg-primary/10 text-primary',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/15 text-warning',
    danger: 'bg-danger/10 text-danger',
    info: 'bg-accent/10 text-accent',
    secondary: 'bg-foreground/10 text-foreground/70',
};

function statusPill(label, variant = 'secondary') {
    const cls = PILL_STYLES[variant] ?? PILL_STYLES.secondary;
    return `<span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider ${cls}">${escapeHtml(label)}</span>`;
}

function relativeFromIso(iso) {
    if (!iso) return null;
    const target = new Date(iso);
    if (Number.isNaN(target.getTime())) return null;
    const now = new Date();
    const startOfTarget = new Date(target.getFullYear(), target.getMonth(), target.getDate()).getTime();
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    const days = Math.round((startOfTarget - startOfToday) / 86400000);
    if (days === 0) return 'today';
    if (days === 1) return 'tomorrow';
    if (days === -1) return 'yesterday';
    if (days > 1) return `in ${days} days`;
    return `${Math.abs(days)} days ago`;
}

function avatarBlock(detail) {
    const initials = detail.student_initials || '?';
    return `
        <div class="flex items-center gap-3">
            <div class="shrink-0 w-11 h-11 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-semibold tracking-wide">
                ${escapeHtml(initials)}
            </div>
            <div class="min-w-0">
                <p class="text-base font-semibold text-foreground leading-tight truncate">${escapeHtml(detail.student_name || 'Unknown')}</p>
                <p class="mt-0.5 text-sm text-foreground/60 truncate">${escapeHtml([detail.school_name, detail.service_name].filter(Boolean).join(' · '))}</p>
            </div>
        </div>
    `;
}

/* ---------- Status-specific bodies ---------- */

function bodyPending(detail) {
    const reminderRel = relativeFromIso(detail.reminder_date_iso);
    const deadlineRel = relativeFromIso(detail.deadline_date_iso);

    return `
        <p class="text-[11px] font-bold uppercase tracking-wider text-foreground/60 mb-3">What happens next</p>
        <div class="space-y-2">
            ${eventCard({
                tone: 'neutral',
                icon: ICONS.mail,
                title: 'Reminder email sent to parent',
                sub: 'Parent can request make-up via the email link',
                date: detail.reminder_date,
                rel: reminderRel,
            })}
            ${eventCard({
                tone: 'warning',
                icon: ICONS.warning,
                title: 'Auto-declines if no response',
                sub: 'Parent has 5 days after reminder to respond',
                date: detail.deadline_date,
                rel: deadlineRel,
            })}
        </div>
        <p class="mt-4 flex items-start gap-2 text-sm text-foreground/60">
            <span class="text-foreground/40 mt-0.5">${ICONS.info}</span>
            <span>You can decline this make-up manually before the reminder is sent if it's no longer needed.</span>
        </p>
    `;
}

function bodySent(detail) {
    const deadlineRel = relativeFromIso(detail.deadline_date_iso);

    return `
        <p class="text-[11px] font-bold uppercase tracking-wider text-foreground/60 mb-3">What happens next</p>
        <div class="space-y-2">
            ${eventCard({
                tone: 'warning',
                icon: ICONS.warning,
                title: 'Auto-declines if no response',
                sub: 'Parent has 5 days after reminder to respond',
                date: detail.deadline_date,
                rel: deadlineRel,
            })}
        </div>
        <p class="mt-4 flex items-start gap-2 text-sm text-foreground/60">
            <span class="text-foreground/40 mt-0.5">${ICONS.info}</span>
            <span>Reminder sent to parent on ${escapeHtml(detail.reminder_sent_at_short || detail.reminder_date)}. Waiting for their response.</span>
        </p>
    `;
}

function bodyRequested(detail) {
    const responseDate = detail.responded_at_short || detail.response_date;
    const reminderDate = detail.reminder_sent_at_short || detail.reminder_date;

    return `
        <div class="rounded-lg bg-success/10 border border-success/15 p-4">
            <div class="flex items-start gap-3">
                <span class="text-success mt-0.5">${ICONS.userCheck}</span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-success">Parent requested this make-up</p>
                    <p class="mt-1 text-sm text-success/80">${escapeHtml(responseDate)} · in response to reminder sent ${escapeHtml(reminderDate)}</p>
                </div>
            </div>
        </div>
        <p class="mt-4 flex items-center gap-2 text-sm text-foreground/70">
            ${ICONS.arrow}
            <span>Schedule a session to complete the make-up</span>
        </p>
    `;
}

function bodyDeclined(detail) {
    const isAuto = detail.decline_kind === 'auto';
    const respondedShort = detail.responded_at_short;

    const bannerTitle = isAuto ? 'Auto-declined — no response from parent' : 'Manually declined';
    const bannerSub = isAuto
        ? (respondedShort ? `Declined automatically on ${respondedShort}` : 'Declined automatically')
        : (respondedShort ? `Declined on ${respondedShort}${detail.responded_by_name ? ` by ${detail.responded_by_name}` : ''}` : 'Declined');

    const info = [];
    if (detail.reminder_sent_at_short) {
        info.push(`<p class="flex items-start gap-2 text-sm text-foreground/70">${ICONS.mail}<span>Reminder sent to parent on ${escapeHtml(detail.reminder_sent_at_short)}</span></p>`);
    }
    if (isAuto) {
        info.push(`<p class="flex items-start gap-2 text-sm text-foreground/70">${ICONS.clockSlash}<span>5-day response window closed without action</span></p>`);
    }
    if (detail.decline_reason) {
        info.push(`<p class="flex items-start gap-2 text-sm text-foreground/70">${ICONS.info}<span>Reason: ${escapeHtml(detail.decline_reason)}</span></p>`);
    }

    return `
        <div class="rounded-lg bg-danger/10 border border-danger/15 p-4">
            <div class="flex items-start gap-3">
                <span class="text-danger mt-0.5">${ICONS.xCircle}</span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-danger">${escapeHtml(bannerTitle)}</p>
                    <p class="mt-1 text-sm text-danger/80">${escapeHtml(bannerSub)}</p>
                </div>
            </div>
        </div>
        ${info.length ? `<div class="mt-4 space-y-2">${info.join('')}</div>` : ''}
    `;
}

function bodyScheduled(detail) {
    const session = detail.makeup_schedule;
    if (!session) {
        return `
            <p class="text-sm text-foreground/70">Make-up session scheduled, but details are unavailable.</p>
        `;
    }

    const timeRange = session.start_time && session.end_time
        ? `${session.start_time} – ${session.end_time}`
        : '';
    const duration = session.duration_minutes ? `${session.duration_minutes} minutes` : '';
    const meta = [timeRange, duration].filter(Boolean).join(' · ');

    return `
        <div class="rounded-lg bg-success/10 border border-success/15 p-5">
            <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-success">
                ${ICONS.calendarSolid}
                Make-up session
            </p>
            <p class="mt-2 text-2xl font-semibold text-success leading-tight">${escapeHtml(session.date || '—')}</p>
            ${meta ? `<p class="mt-1 text-sm text-success/80">${escapeHtml(meta)}</p>` : ''}
        </div>
    `;
}

function bodyFailed(detail) {
    return `
        <div class="rounded-lg bg-danger/10 border border-danger/15 p-4">
            <div class="flex items-start gap-3">
                <span class="text-danger mt-0.5">${ICONS.xCircle}</span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-danger">Reminder email failed to send</p>
                    <p class="mt-1 text-sm text-danger/80">The parent reminder couldn't be delivered. Contact the parent directly or try again.</p>
                </div>
            </div>
        </div>
        <p class="mt-4 flex items-start gap-2 text-sm text-foreground/60">
            <span class="text-foreground/40 mt-0.5">${ICONS.info}</span>
            <span>Originally scheduled to send on ${escapeHtml(detail.reminder_date)}.</span>
        </p>
    `;
}

function eventCard({ tone, icon, title, sub, date, rel }) {
    const toneClasses = tone === 'warning'
        ? 'bg-warning/10 border-warning/15'
        : 'bg-muted/40 border-border';
    return `
        <div class="rounded-lg border ${toneClasses} p-4">
            <div class="flex items-start gap-3">
                <span class="text-foreground/70 mt-0.5">${icon}</span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold text-foreground">${escapeHtml(title)}</p>
                        <p class="text-sm font-semibold text-foreground text-right shrink-0">${escapeHtml(date || '')}</p>
                    </div>
                    <div class="flex items-start justify-between gap-3 mt-0.5">
                        <p class="text-xs text-foreground/60">${escapeHtml(sub)}</p>
                        ${rel ? `<p class="text-xs text-foreground/60 text-right shrink-0">${escapeHtml(rel)}</p>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderBodyForStatus(detail) {
    switch (detail.status) {
        case 'pending': return bodyPending(detail);
        case 'sent': return bodySent(detail);
        case 'requested': return bodyRequested(detail);
        case 'declined': return bodyDeclined(detail);
        case 'scheduled': return bodyScheduled(detail);
        case 'failed': return bodyFailed(detail);
        default: return `<p class="text-sm text-foreground/70">Status: ${escapeHtml(detail.status_label)}</p>`;
    }
}

function renderFooter(detail) {
    const left = [];
    const right = [];

    if (detail.status === 'pending' && detail.can_decline) {
        left.push(`<button type="button" data-makeup-decline-url="${escapeAttr(detail.decline_url)}"
            class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg border border-border text-danger hover:bg-danger/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"/></svg>
            Decline manually
        </button>`);
    }

    right.push(`<button type="button" x-on:click="$dispatch('close-modal', 'makeup-request-detail')"
        class="inline-flex items-center gap-2 text-sm font-medium px-5 py-2 rounded-lg border border-border text-foreground hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        Close
    </button>`);

    if (detail.status === 'requested' && detail.can_book) {
        right.push(`<a href="${escapeAttr(detail.book_url)}"
            class="inline-flex items-center gap-2 text-sm font-medium px-5 py-2 rounded-lg bg-foreground text-background hover:bg-foreground/90 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            ${ICONS.calendar}
            Schedule session
        </a>`);
    }

    return `
        <div class="flex-1">${left.join('')}</div>
        <div class="flex items-center gap-2">${right.join('')}</div>
    `;
}

function renderDetail(detail) {
    const statusEl = document.getElementById('makeup-modal-status-pill');
    if (statusEl) {
        statusEl.innerHTML = statusPill(detail.status_label, detail.status_variant);
    }

    const subtitle = document.getElementById('makeup-modal-subtitle');
    if (subtitle) {
        subtitle.innerHTML = `
            <span class="text-foreground/50">${ICONS.calendar}</span>
            <span>${escapeHtml(detail.closure_title || '—')} · ${escapeHtml(detail.event_date || '')}</span>
        `;
    }

    const student = document.getElementById('makeup-modal-student');
    if (student) {
        student.innerHTML = avatarBlock(detail);
    }

    const body = document.getElementById('makeup-modal-body');
    if (body) {
        body.innerHTML = renderBodyForStatus(detail);
    }

    const footer = document.getElementById('makeup-modal-footer');
    if (footer) {
        footer.innerHTML = renderFooter(detail);
    }
}

async function openDetail(id) {
    showLoading('Loading...');

    try {
        const response = await fetch(`/therapist/makeup-requests/${id}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });

        closeAlert();

        if (!response.ok) {
            const data = await response.json().catch(() => ({ message: 'Failed to load details.' }));
            throw new Error(data.message ?? 'Failed to load details.');
        }

        const payload = await response.json();
        const detail = payload.data ?? payload;
        renderDetail(detail);
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'makeup-request-detail' }));
    } catch (error) {
        errorAlert(error instanceof Error ? error.message : 'Failed to load details.');
    }
}

function bindViewHandler() {
    document.body.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-makeup-view]');
        if (!button) return;

        event.preventDefault();
        const id = button.getAttribute('data-makeup-view');
        if (!id) return;

        openDetail(id);
    });
}

function bindDeclineHandler() {
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-makeup-decline-url]');
        if (!button) return;

        event.preventDefault();

        const result = await confirmDialog({
            title: 'Decline Make-Up Request?',
            text: 'This will mark the request as declined on behalf of the parent.',
            icon: 'warning',
            confirmButtonText: 'Yes, decline',
            showInput: true,
            inputPlaceholder: 'Optional reason...',
        });

        if (!result.isConfirmed) return;

        const url = button.getAttribute('data-makeup-decline-url') ?? '';

        showLoading('Declining...');

        try {
            const formData = new FormData();
            if (result.value) {
                formData.append('reason', result.value);
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: formData,
            });

            closeAlert();

            if (!response.ok) {
                const data = await response.json().catch(() => ({ message: 'Failed to decline.' }));
                throw new Error(data.message ?? 'Failed to decline.');
            }

            await successToast('Make-up request declined.');
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'makeup-request-detail' }));
            if (dataTableInstance) {
                dataTableInstance.ajax.reload();
            }
        } catch (error) {
            errorAlert(error instanceof Error ? error.message : 'An error occurred.');
        }
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    await initMakeupRequestsTable();
    bindStatusFilter();
    bindViewHandler();
    bindDeclineHandler();
});
