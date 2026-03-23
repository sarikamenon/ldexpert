import $ from 'jquery';
import { errorAlert } from '../common/sweetalert';

$(function () {
    const $ssaSelect = $('#session-log-ssa');
    const $studentName = $('#session-log-student-name');
    const $studentId = $('input[name="student_id"]');
    const $serviceSelect = $('#session-log-service');
    const $dateInput = $('#session-log-date');
    const $startTime = $('#session-log-start-time');
    const $duration = $('#session-log-duration');
    const $endTime = $('#session-log-end-time');

    const configElement = document.getElementById('session-log-config');
    const hasSchedule = $('input[name="schedule_id"]').length > 0;

    let config = {
        ssaServiceMappings: [],
    };

    if (configElement) {
        try {
            config = JSON.parse(configElement.textContent || '{}');
        } catch (e) {
            console.error('Failed to parse session log config', e);
        }
    }

    const ssaServiceIndex = {};
    if (Array.isArray(config.ssaServiceMappings)) {
        config.ssaServiceMappings.forEach((mapping) => {
            ssaServiceIndex[String(mapping.ssa_id)] = mapping;
        });
    }

    function updateEndTime() {
        const dateVal = $dateInput.val();
        const startVal = $startTime.val();
        const durationVal = parseInt($duration.val() || '0', 10);

        if (!dateVal || !startVal || !durationVal) {
            return;
        }

        const [startHour, startMinute] = startVal.split(':').map((v) => parseInt(v, 10));
        if (Number.isNaN(startHour) || Number.isNaN(startMinute)) {
            return;
        }

        const date = new Date(dateVal + 'T' + startVal + ':00');
        date.setMinutes(date.getMinutes() + durationVal);

        const endHour = String(date.getHours()).padStart(2, '0');
        const endMinute = String(date.getMinutes()).padStart(2, '0');

        $endTime.val(`${endHour}:${endMinute}`);
    }

    function applySsaConstraints() {
        const ssaId = $ssaSelect.val();
        if (!ssaId) {
            return;
        }

        const option = $ssaSelect.find('option:selected');
        const mapping = ssaServiceIndex[String(ssaId)];

        const studentId = option.data('student-id');
        const studentName = option.data('student-name');
        const startDate = option.data('start-date');
        const endDate = option.data('end-date');

        if (!hasSchedule && $studentId.length && $studentName.length) {
            if (studentId) {
                $studentId.val(studentId);
            }
            if (studentName) {
                $studentName.val(studentName);
            }
        }

        if (startDate) {
            $dateInput.attr('min', startDate);
        }
        if (endDate) {
            $dateInput.attr('max', endDate);
        }
        if (!hasSchedule && !$dateInput.val() && startDate) {
            $dateInput.val(startDate);
        }

        if (mapping && Array.isArray(mapping.services) && $serviceSelect.length) {
            $serviceSelect.empty();
            $serviceSelect.append(
                $('<option>', { value: '', text: 'Select service' }),
            );
            mapping.services.forEach((service) => {
                $serviceSelect.append(
                    $('<option>', {
                        value: service.id,
                        text: service.name,
                    }),
                );
            });

            // Auto-select primary service from SSA
            if (mapping.primary_service_id) {
                $serviceSelect.val(String(mapping.primary_service_id));
            }
        }
    }

    $ssaSelect.on('change', () => {
        applySsaConstraints();
    });

    $startTime.on('change', () => updateEndTime());
    $duration.on('change keyup', () => updateEndTime());
    $dateInput.on('change', () => updateEndTime());

    if (!hasSchedule) {
        applySsaConstraints();
    }

    updateEndTime();

    // Show validation errors as SweetAlert on page load (after redirect back)
    const errorsElement = document.getElementById('session-log-errors');
    if (errorsElement) {
        try {
            const errors = JSON.parse(errorsElement.textContent || '[]');
            if (errors.length > 0) {
                errorAlert(errors.join('\n'), 'Validation Error');
            }
        } catch (e) {
            console.error('Failed to parse session log errors', e);
        }
    }

    // Billing entry window check
    const sessionDate = $('input[name="session_date"]').val();
    if (sessionDate) {
        $.get('/therapist/session-logs/entry-window', { session_date: sessionDate })
            .done(function (data) {
                if (data.is_within_window === false) {
                    $('#billing-window-cutoff').text(data.cutoff_display || data.cutoff);
                    $('#billing-window-blocked').removeClass('hidden');

                    // Disable submit button
                    $('form button[type="submit"], form .loading-button, form [x-on\\:click]')
                        .filter(':last')
                        .prop('disabled', true)
                        .addClass('opacity-50 pointer-events-none');

                    errorAlert(
                        'The billing window for this session\'s week closed on ' +
                        (data.cutoff_display || data.cutoff) +
                        '. You can no longer create or edit session logs for this date. Please contact an administrator.',
                        'Billing Window Closed'
                    );
                }
            });
    }
});


