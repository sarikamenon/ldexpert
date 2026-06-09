import { initFullCalendar, refetchCalendarEvents } from '../common/fullcalendar';
import { openScheduleDetailsModal, bindDeleteHandler, bindDeleteFutureHandler } from '../common/schedule-modal';
import { initSelectBoxes } from '../common/select-box';

(function ($) {
    'use strict';

    $(document).ready(function () {
        initSelectBoxes();

        const calendarEl = document.getElementById('fullCalendar');
        if (!calendarEl) return;

        const eventsUrl   = calendarEl.dataset.eventsUrl;
        const detailsUrl  = calendarEl.dataset.detailsUrl;
        const editUrl     = calendarEl.dataset.editUrl;
        const deleteUrl   = calendarEl.dataset.deleteUrl;
        const studentUrl  = calendarEl.dataset.studentUrl;

        const calendar = initFullCalendar(calendarEl, {
            eventsUrl: eventsUrl,
            initialView: 'dayGridMonth',
            showSessionLogIndicators: true,
            onEventClick: function (event) {
                const props = event.extendedProps;
                if (props.type === 'session_log' && props.session_log_id) {
                    window.open(`/admin/session-logs/${props.session_log_id}`, '_blank', 'noopener');
                    return;
                }

                const scheduleId = props.schedule_id;
                openScheduleDetailsModal(scheduleId, detailsUrl, {
                    studentUrl: (id) => `${studentUrl}/${id}`,
                    editUrl: editUrl ? (id) => `${editUrl}/${id}/edit` : undefined,
                });
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

        // Wire delete buttons rendered by the modal footer
        if (deleteUrl) {
            bindDeleteHandler(deleteUrl, () => {
                refetchCalendarEvents(calendar);
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'scheduleDetailsModal' }));
            });

            bindDeleteFutureHandler(deleteUrl, () => {
                refetchCalendarEvents(calendar);
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'scheduleDetailsModal' }));
            });
        }

        // Add Schedule: therapist + SSA selection modal
        wireAddScheduleModal($);

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

    /**
     * Wire the "Add New Schedule" modal: pick a therapist, lazy-load their active
     * SSAs, then navigate to the create form with both ids in the query string.
     */
    function wireAddScheduleModal($) {
        const $modal = $('#adminScheduleSelectionModal');
        const $form = $('#adminScheduleSelectionForm');
        if (!$modal.length || !$form.length) return;

        const $therapist = $('#modal_therapist_id');
        const $ssa = $('#modal_ssa_id');
        const $continue = $('#adminScheduleSelectionContinue');
        const createBase = $form.data('create-base');
        const ssasUrl = $form.data('ssas-url');

        const resetSsa = (placeholder) => {
            $ssa.prop('disabled', true).empty().append($('<option>', { value: '', text: placeholder }));
            $ssa.trigger('change.select2');
            $continue.prop('disabled', true);
        };

        const openModal = () => {
            $therapist.val('').trigger('change.select2');
            resetSsa('Select a therapist first');
            $modal.removeClass('hidden');
        };

        const closeModal = () => $modal.addClass('hidden');

        $('#addScheduleButton').on('click', openModal);
        $('#cancelAdminScheduleSelection').on('click', closeModal);
        $modal.on('click', (e) => {
            if (e.target === $modal[0]) closeModal();
        });

        $therapist.on('change', function () {
            const therapistId = $(this).val();
            if (!therapistId) {
                resetSsa('Select a therapist first');
                return;
            }

            resetSsa('Loading SSAs…');

            $.ajax({
                url: ssasUrl,
                method: 'GET',
                data: { therapist_id: therapistId },
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            })
                .done((data) => {
                    if (!Array.isArray(data) || data.length === 0) {
                        resetSsa('No active SSAs for this therapist');
                        return;
                    }
                    $ssa.empty().append($('<option>', { value: '', text: 'Select an SSA' }));
                    data.forEach((s) => {
                        $ssa.append($('<option>', { value: s.id, text: s.label }));
                    });
                    $ssa.prop('disabled', false).trigger('change.select2');
                })
                .fail(() => resetSsa('Failed to load SSAs'));
        });

        $ssa.on('change', function () {
            $continue.prop('disabled', !$(this).val());
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            const therapistId = $therapist.val();
            const ssaId = $ssa.val();
            if (!therapistId || !ssaId) return;

            const url = new URL(createBase, window.location.origin);
            url.searchParams.set('therapist_id', therapistId);
            url.searchParams.set('ssa_id', ssaId);
            window.location.href = url.toString();
        });
    }
})(window.jQuery);
