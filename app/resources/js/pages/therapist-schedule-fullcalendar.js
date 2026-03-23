import { initFullCalendar, refetchCalendarEvents } from '../common/fullcalendar';
import { openScheduleDetailsModal, bindDeleteHandler } from '../common/schedule-modal';

(function ($) {
    'use strict';

    $(document).ready(function () {
        const calendarEl = document.getElementById('fullCalendar');
        if (!calendarEl) return;

        const eventsUrl = calendarEl.dataset.eventsUrl;
        const detailsUrl = '/therapist/schedule';

        const calendar = initFullCalendar(calendarEl, {
            eventsUrl: eventsUrl,
            initialView: 'timeGridWeek',
            onEventClick: function (event) {
                const scheduleId = event.extendedProps.schedule_id;
                openScheduleDetailsModal(scheduleId, detailsUrl, {
                    editUrl: (id) => `/therapist/schedule/${id}/edit`,
                    billUrl: (id) => `/therapist/session-logs/create/schedule/${id}`,
                });
            },
        });

        // Delete handler for therapist
        bindDeleteHandler('/therapist/schedule', function () {
            refetchCalendarEvents(calendar);
        });
    });
})(window.jQuery);
