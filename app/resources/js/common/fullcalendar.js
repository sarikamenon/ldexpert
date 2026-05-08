import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

/**
 * Initialize a FullCalendar instance.
 *
 * @param {HTMLElement} calendarEl - The DOM element to render into
 * @param {Object} options - Configuration options
 * @param {string} options.eventsUrl - AJAX URL for fetching events
 * @param {Function} options.onEventClick - Callback when an event is clicked
 * @param {Function} [options.getExtraParams] - Returns extra query params for event fetching
 * @param {string} [options.initialView] - Default view (default: 'timeGridWeek')
 * @returns {Calendar} The FullCalendar instance
 */
export function initFullCalendar(calendarEl, options) {
    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        initialView: options.initialView || 'timeGridWeek',
        headerToolbar: {
            left: 'prev,today,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
        },
        height: 'auto',
        navLinks: true,
        editable: false,
        selectable: false,
        nowIndicator: true,
        slotMinTime: '06:00:00',
        slotMaxTime: '21:00:00',
        allDaySlot: false,
        eventDisplay: 'block',
        weekends: true,
        slotDuration: '00:30:00',
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short',
        },
        events: function (info, successCallback, failureCallback) {
            const params = new URLSearchParams({
                start: info.startStr.split('T')[0],
                end: info.endStr.split('T')[0],
            });

            if (typeof options.getExtraParams === 'function') {
                const extra = options.getExtraParams();
                Object.entries(extra).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach((v) => params.append(`${key}[]`, String(v)));
                    } else if (value !== null && value !== undefined && value !== '') {
                        params.append(key, String(value));
                    }
                });
            }

            fetch(`${options.eventsUrl}?${params.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch events');
                    }
                    return response.json();
                })
                .then((data) => successCallback(data))
                .catch((error) => failureCallback(error));
        },
        eventClick: function (info) {
            if (typeof options.onEventClick === 'function') {
                options.onEventClick(info.event);
            }
        },
        eventDidMount: function (info) {
            const props = info.event.extendedProps;
            if (props.is_group) {
                info.el.classList.add('fc-event-group');
            }
            if (props.is_past && props.billing_status === 'pending') {
                info.el.classList.add('fc-event-needs-billing');
            }
            if (props.type === 'session_log') {
                info.el.classList.add('fc-event-orphan-log');
            }

            if (options.showSessionLogIndicators) {
                const logStatus = props.session_log_status;
                if (!props.is_past && !logStatus) return;

                const SESSION_LOG_ICONS = {
                    missing:   { path: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', title: 'Session log pending submission', cssClass: 'fc-log-missing' },
                    draft:     { path: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', title: 'Session log draft', cssClass: 'fc-log-draft' },
                    sent_back: { path: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6', title: 'Session log sent back', cssClass: 'fc-log-sent-back' },
                    submitted: { path: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12', title: 'Session log submitted', cssClass: 'fc-log-submitted' },
                    approved:  { path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', title: 'Session log approved', cssClass: 'fc-log-approved' },
                    cancelled: { path: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z', title: 'Session log cancelled', cssClass: 'fc-log-cancelled' },
                };

                const key = !props.has_session_log && props.is_past
                    ? 'missing'
                    : (logStatus in SESSION_LOG_ICONS ? logStatus : null);

                if (!key) return;

                const { path, title, cssClass } = SESSION_LOG_ICONS[key];
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '2');
                svg.setAttribute('stroke-linecap', 'round');
                svg.setAttribute('stroke-linejoin', 'round');
                svg.classList.add('fc-session-log-icon', cssClass);
                svg.setAttribute('aria-label', title);
                svg.setAttribute('title', title);
                const pathEl = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                pathEl.setAttribute('d', path);
                svg.appendChild(pathEl);

                const mountEl = info.el.querySelector('.fc-event-main') ?? info.el;
                mountEl.appendChild(svg);
            }
        },
    });

    calendar.render();
    return calendar;
}

/**
 * Refetch events on the calendar (call after filter changes).
 *
 * @param {Calendar} calendar
 */
export function refetchCalendarEvents(calendar) {
    if (calendar) {
        calendar.refetchEvents();
    }
}
