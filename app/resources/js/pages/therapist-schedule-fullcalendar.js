import { initFullCalendar, refetchCalendarEvents } from '../common/fullcalendar';
import { openScheduleDetailsModal, bindDeleteHandler, bindDeleteFutureHandler } from '../common/schedule-modal';
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

        // Apply filters on button click
        $('#applyCalendarFilters').on('click', function () {
            refetchCalendarEvents(calendar);
        });

        // Clear all filters and refetch
        $('#clearCalendarFilters').on('click', function () {
            $('#filter_student_ids').val(null).trigger('change.select2');
            $('#filter_status').val(null).trigger('change.select2');
            $('#filter_billing_status').val(null).trigger('change.select2');
            refetchCalendarEvents(calendar);
        });

        // Delete handler for therapist
        bindDeleteHandler('/therapist/schedule', function () {
            refetchCalendarEvents(calendar);
        });

        // Delete future recurring schedules handler
        bindDeleteFutureHandler('/therapist/schedule', function () {
            refetchCalendarEvents(calendar);
        });

        // Add New Schedule - SSA selection modal
        const addScheduleButton = document.getElementById('addScheduleButton');
        const ssaSelectionModal = document.getElementById('ssaSelectionModal');
        const ssaSelectionForm = document.getElementById('ssaSelectionForm');
        const cancelSSASelection = document.getElementById('cancelSSASelection');

        if (addScheduleButton && ssaSelectionModal) {
            addScheduleButton.addEventListener('click', (e) => {
                e.preventDefault();
                if (addScheduleButton.disabled) return;

                // Get the currently selected date from FullCalendar, fallback to today
                const currentDate = calendar.getDate();
                const dateStr = currentDate.toISOString().split('T')[0];
                ssaSelectionModal.dataset.scheduleDate = dateStr;
                ssaSelectionModal.classList.remove('hidden');
                initSelectBoxes();
            });
        }

        if (cancelSSASelection && ssaSelectionModal) {
            cancelSSASelection.addEventListener('click', () => {
                ssaSelectionModal.classList.add('hidden');
            });
        }

        if (ssaSelectionForm && ssaSelectionModal) {
            ssaSelectionForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const ssaId = document.getElementById('ssa_id')?.value;
                if (!ssaId) return;

                const dateStr = ssaSelectionModal.dataset.scheduleDate
                    || new Date().toISOString().split('T')[0];

                const baseUrl = addScheduleButton?.dataset.createBase || '/therapist/schedule/create';
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('date', dateStr);
                url.searchParams.set('ssa_id', ssaId);

                window.location.href = url.toString();
            });

            // Close modal when clicking outside
            ssaSelectionModal.addEventListener('click', (e) => {
                if (e.target === ssaSelectionModal) {
                    ssaSelectionModal.classList.add('hidden');
                }
            });
        }
    });
})(window.jQuery);
