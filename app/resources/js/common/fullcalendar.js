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
