import { initFullCalendar, refetchCalendarEvents } from '../common/fullcalendar';
import { openScheduleDetailsModal, bindDeleteHandler } from '../common/schedule-modal';
import { initSelectBoxes } from '../common/select-box';

(function ($) {
    'use strict';

    $(document).ready(function () {
        initSelectBoxes();

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
            getExtraParams: function () {
                return {
                    student_ids: $('#filter_student_ids').val() || [],
                    status: $('#filter_status').val() || '',
                    billing_status: $('#filter_billing_status').val() || '',
                };
            },
        });

        // Wire filter changes to refetch events
        $('#scheduleCalendarFilters').on('change', 'select', function () {
            refetchCalendarEvents(calendar);
        });

        // Delete handler for therapist
        bindDeleteHandler('/therapist/schedule', function () {
            refetchCalendarEvents(calendar);
        });
    });
})(window.jQuery);
