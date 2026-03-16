import { initFullCalendar, refetchCalendarEvents } from '../common/fullcalendar';
import { openScheduleDetailsModal } from '../common/schedule-modal';
import { initSelectBoxes } from '../common/select-box';

(function ($) {
    'use strict';

    $(document).ready(function () {
        initSelectBoxes();

        const calendarEl = document.getElementById('fullCalendar');
        if (!calendarEl) return;

        const eventsUrl = calendarEl.dataset.eventsUrl;
        const detailsUrl = calendarEl.dataset.detailsUrl;

        const calendar = initFullCalendar(calendarEl, {
            eventsUrl: eventsUrl,
            initialView: 'timeGridWeek',
            onEventClick: function (event) {
                const scheduleId = event.extendedProps.schedule_id;
                // Admin view is read-only — no action URLs
                openScheduleDetailsModal(scheduleId, detailsUrl, {});
            },
            getExtraParams: function () {
                return {
                    therapist_id: $('#filter_therapist_id').val() || '',
                    student_id: $('#filter_student_id').val() || '',
                    school_id: $('#filter_school_id').val() || '',
                    status: $('#filter_status').val() || '',
                    billing_status: $('#filter_billing_status').val() || '',
                };
            },
        });

        // Wire filter changes to refetch events
        $('#scheduleCalendarFilters').on('change', 'select', function () {
            refetchCalendarEvents(calendar);
        });
    });
})(window.jQuery);
