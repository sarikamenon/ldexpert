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
            showSessionLogIndicators: true,
            onEventClick: function (event) {
                const props = event.extendedProps;
                if (props.type === 'session_log' && props.session_log_id) {
                    window.open(`/admin/session-logs/${props.session_log_id}`, '_blank', 'noopener');
                    return;
                }

                const scheduleId = props.schedule_id;
                // Admin view is read-only — no action URLs
                openScheduleDetailsModal(scheduleId, detailsUrl, {});
            },
            getExtraParams: function () {
                return {
                    therapist_ids: $('#filter_therapist_ids').val() || [],
                    student_id: $('#filter_student_id').val() || '',
                    school_id: $('#filter_school_id').val() || '',
                    status: $('#filter_status').val() || '',
                    billing_status: $('#filter_billing_status').val() || '',
                };
            },
        });

        // Apply filters on button click
        $('#applyCalendarFilters').on('click', function () {
            refetchCalendarEvents(calendar);
        });

        // Clear all filters and refetch
        $('#clearCalendarFilters').on('click', function () {
            $('#filter_therapist_ids').val(null).trigger('change.select2');
            $('#filter_student_id').val(null).trigger('change.select2');
            $('#filter_school_id').val(null).trigger('change.select2');
            $('#filter_status').val(null).trigger('change.select2');
            $('#filter_billing_status').val(null).trigger('change.select2');
            refetchCalendarEvents(calendar);
        });
    });
})(window.jQuery);
