/**
 * SSA Form JavaScript
 * Handles THO minutes auto-calculation, calculated minutes, conditional field display,
 * and therapist filtering by selected service.
 */

import $ from 'jquery';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('ssaForm');
    if (!form) {
        return;
    }

    // Get services data
    const servicesDataEl = document.getElementById('services-data');
    const servicesData = servicesDataEl ? JSON.parse(servicesDataEl.textContent) : {};

    // Get current service data if editing
    const currentServiceDataEl = document.getElementById('current-service-data');
    const currentServiceData = currentServiceDataEl ? JSON.parse(currentServiceDataEl.textContent) : null;

    const primaryServiceId = document.getElementById('primary_service_id');
    const assignedTherapistId = document.getElementById('assigned_therapist_id');
    const minutesPerSession = document.getElementById('minutes_per_session');
    const frequency = document.getElementById('frequency');
    const sessionsPerFrequency = document.getElementById('sessions_per_frequency');
    const sessionsPerFrequencyHidden = document.getElementById('sessions_per_frequency_hidden');
    const sessionsPerFrequencyLock = document.getElementById('sessions_per_frequency_lock');
    const sessionsPerFrequencyStatus = document.getElementById('sessions_per_frequency_status');
    const calculatedMinutes = document.getElementById('calculated_minutes');
    const calculatedMinutesHidden = document.getElementById('calculated_minutes_hidden');
    const calculatedMinutesLock = document.getElementById('calculated_minutes_lock');
    const calculatedMinutesStatus = document.getElementById('calculated_minutes_status');
    const adjustedMinutes = document.getElementById('adjusted_minutes');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const thoMinutes = document.getElementById('tho_minutes');
    const thoCalculationHint = document.getElementById('tho-calculation-hint');

    // Therapist filtering by service
    const therapistsForServiceUrlEl = document.getElementById('therapists-for-service-url');
    const therapistsForServiceUrl = therapistsForServiceUrlEl ? JSON.parse(therapistsForServiceUrlEl.textContent) : null;
    const currentAssignedTherapistIdEl = document.getElementById('current-assigned-therapist-id');
    const currentAssignedTherapistId = currentAssignedTherapistIdEl ? JSON.parse(currentAssignedTherapistIdEl.textContent) : null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Field containers for conditional display
    const frequencyFields = document.getElementById('frequency-fields');
    const frequencyField = document.getElementById('frequency-field');
    const sessionsPerFrequencyField = document.getElementById('sessions-per-frequency-field');
    const calculatedMinutesField = document.getElementById('calculated-minutes-field');
    const adjustedMinutesField = document.getElementById('adjusted-minutes-field');
    const ONE_TIME_FREQUENCY = 'one_time';
    const defaultThoCalculationHint = 'Auto-calculated: Minutes per Session × (Sessions per Frequency × Number of Frequencies in Date Range)';
    const oneTimeThoCalculationHint = 'Auto-calculated: Minutes per Session + Adjusted Minutes';
    const oneTimeLockMessage = 'Auto-set to 1 for One Time.';
    const oneTimeCalculatedMessage = 'Auto-set from Minutes per Session for One Time.';

    const frequencyMultipliers = {
        weekly: 52 / 365,
        bi_weekly: 26 / 365,
        monthly: 12 / 365,
        quarterly: 4 / 365,
    };

    function getPositiveIntegerValue(field) {
        if (!field?.value) {
            return null;
        }

        const value = parseInt(field.value, 10);
        return Number.isNaN(value) || value <= 0 ? null : value;
    }

    function supportsFrequencyBasedScheduling() {
        if (!primaryServiceId?.value) {
            return false;
        }

        const serviceId = parseInt(primaryServiceId.value, 10);
        return servicesData[serviceId] === true || servicesData[serviceId] === 1;
    }

    function isOneTimeFrequencySelected() {
        return supportsFrequencyBasedScheduling() && frequency?.value === ONE_TIME_FREQUENCY;
    }

    function updateHiddenField(hiddenField, value, enabled) {
        if (!hiddenField) {
            return;
        }

        hiddenField.value = value;
        hiddenField.disabled = !enabled;
    }

    function updateLockedFieldPresentation(field, lockIcon, statusElement, locked, statusMessage) {
        if (field) {
            field.classList.toggle('cursor-not-allowed', locked);
            field.classList.toggle('bg-background', locked);
            field.title = locked ? statusMessage : '';
        }

        if (lockIcon) {
            lockIcon.classList.toggle('hidden', !locked);
        }

        if (statusElement) {
            statusElement.textContent = locked ? statusMessage : '';
            statusElement.classList.toggle('hidden', !locked);
        }
    }

    function applyOneTimeFrequencyFields() {
        const isOneTimeFrequency = isOneTimeFrequencySelected();
        const supportsFrequency = supportsFrequencyBasedScheduling();
        const minutesValue = getPositiveIntegerValue(minutesPerSession);
        const oneTimeCalculatedMinutes = minutesValue ? String(minutesValue) : '';

        if (sessionsPerFrequency) {
            if (isOneTimeFrequency) {
                sessionsPerFrequency.value = '1';
                sessionsPerFrequency.disabled = true;
                sessionsPerFrequency.required = false;
                updateLockedFieldPresentation(
                    sessionsPerFrequency,
                    sessionsPerFrequencyLock,
                    sessionsPerFrequencyStatus,
                    true,
                    oneTimeLockMessage
                );
                updateHiddenField(sessionsPerFrequencyHidden, '1', true);
            } else {
                sessionsPerFrequency.disabled = false;
                sessionsPerFrequency.required = supportsFrequency;
                updateLockedFieldPresentation(
                    sessionsPerFrequency,
                    sessionsPerFrequencyLock,
                    sessionsPerFrequencyStatus,
                    false,
                    ''
                );
                updateHiddenField(sessionsPerFrequencyHidden, sessionsPerFrequency.value, false);
            }
        }

        if (calculatedMinutes) {
            if (isOneTimeFrequency) {
                calculatedMinutes.value = oneTimeCalculatedMinutes;
                calculatedMinutes.disabled = true;
                updateLockedFieldPresentation(
                    calculatedMinutes,
                    calculatedMinutesLock,
                    calculatedMinutesStatus,
                    true,
                    oneTimeCalculatedMessage
                );
                updateHiddenField(calculatedMinutesHidden, oneTimeCalculatedMinutes, true);
            } else {
                calculatedMinutes.disabled = false;
                updateLockedFieldPresentation(
                    calculatedMinutes,
                    calculatedMinutesLock,
                    calculatedMinutesStatus,
                    false,
                    ''
                );
                updateHiddenField(calculatedMinutesHidden, calculatedMinutes.value, false);
            }
        }

        if (thoCalculationHint) {
            thoCalculationHint.textContent = isOneTimeFrequency
                ? oneTimeThoCalculationHint
                : defaultThoCalculationHint;
        }
    }

    function getNumberOfFrequencies() {
        if (isOneTimeFrequencySelected()) {
            return 1;
        }

        if (!frequency?.value || !startDate?.value || !endDate?.value) {
            return null;
        }

        const start = new Date(startDate.value);
        const end = new Date(endDate.value);

        if (!(start instanceof Date && !Number.isNaN(start.valueOf())) ||
            !(end instanceof Date && !Number.isNaN(end.valueOf())) ||
            start >= end) {
            return null;
        }

        const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        const multiplier = frequencyMultipliers[frequency.value] || 0;

        if (multiplier <= 0) {
            return null;
        }

        const numberOfFrequencies = Math.ceil(daysDiff * multiplier);
        return numberOfFrequencies > 0 ? numberOfFrequencies : null;
    }

    function updateCalculatedMinutesValue() {
        if (!supportsFrequencyBasedScheduling()) {
            if (calculatedMinutes) {
                calculatedMinutes.value = '';
            }
            return;
        }

        if (isOneTimeFrequencySelected()) {
            const minutesValue = getPositiveIntegerValue(minutesPerSession);

            if (calculatedMinutes) {
                calculatedMinutes.value = minutesValue ? String(minutesValue) : '';
                updateHiddenField(calculatedMinutesHidden, calculatedMinutes.value, true);
            }
            return;
        }

        const minutesValue = getPositiveIntegerValue(minutesPerSession);
        const sessionsValue = getPositiveIntegerValue(sessionsPerFrequency);
        const numberOfFrequencies = getNumberOfFrequencies();

        if (calculatedMinutes && minutesValue && sessionsValue && numberOfFrequencies) {
            const totalSessions = sessionsValue * numberOfFrequencies;
            calculatedMinutes.value = String(totalSessions * minutesValue);
        } else if (calculatedMinutes) {
            calculatedMinutes.value = '';
        }
    }

    function updateThoMinutesValue() {
        const supportsFrequency = supportsFrequencyBasedScheduling();

        if (!supportsFrequency) {
            return;
        }

        const minutesValue = getPositiveIntegerValue(minutesPerSession);
        const sessionsValue = getPositiveIntegerValue(sessionsPerFrequency);
        const numberOfFrequencies = getNumberOfFrequencies();

        if (!minutesValue || !sessionsValue || !numberOfFrequencies) {
            return;
        }

        let calculatedThoMinutes = numberOfFrequencies * sessionsValue * minutesValue;

        if (adjustedMinutes?.value) {
            const adjusted = parseInt(adjustedMinutes.value, 10);
            if (!Number.isNaN(adjusted)) {
                calculatedThoMinutes += adjusted;
            }
        }

        if (thoMinutes && calculatedThoMinutes > 0) {
            const calculatedThoHours = (calculatedThoMinutes / 60).toFixed(2);
            thoMinutes.value = String(calculatedThoHours);
        }
    }

    function refreshSchedulingState({ toggleVisibility = false } = {}) {
        if (toggleVisibility) {
            toggleFrequencyFields();
        }

        applyOneTimeFrequencyFields();
        updateCalculatedMinutesValue();
        updateThoMinutesValue();
    }

    function bindNativeListeners(field, eventNames, callback) {
        if (!field) {
            return;
        }

        eventNames.forEach((eventName) => {
            field.addEventListener(eventName, callback);
        });
    }

    function bindSelectListeners(field, callback) {
        if (!field) {
            return;
        }

        bindNativeListeners(field, ['change', 'blur'], callback);

        if (window.jQuery) {
            window.jQuery(field).on('change select2:select select2:clear', callback);
        }
    }

    function bindInputListeners(field, callback) {
        bindNativeListeners(field, ['change', 'input', 'blur'], callback);
    }

    // Check if service supports frequency and toggle fields
    function toggleFrequencyFields() {
        if (!primaryServiceId?.value) {
            return;
        }

        const supportsFrequency = supportsFrequencyBasedScheduling();

        if (supportsFrequency) {
            // Show all frequency-related fields
            if (frequencyField) {
                frequencyField.style.display = 'block';
                const frequencySelect = frequencyField.querySelector('select');
                if (frequencySelect) {
                    frequencySelect.required = true;
                }
            }
            if (sessionsPerFrequencyField) {
                sessionsPerFrequencyField.style.display = 'block';
                const sessionsInput = sessionsPerFrequencyField.querySelector('input');
                if (sessionsInput) {
                    sessionsInput.required = true;
                }
            }
            if (calculatedMinutesField) {
                calculatedMinutesField.style.display = 'block';
            }
            if (adjustedMinutesField) {
                adjustedMinutesField.style.display = 'block';
            }
            if (thoCalculationHint) {
                thoCalculationHint.textContent = defaultThoCalculationHint;
            }
        } else {
            // Hide frequency-related fields, only show THO Minutes
            if (frequencyField) {
                frequencyField.style.display = 'none';
                const frequencySelect = frequencyField.querySelector('select');
                if (frequencySelect) {
                    frequencySelect.required = false;
                    frequencySelect.value = '';
                }
            }
            if (sessionsPerFrequencyField) {
                sessionsPerFrequencyField.style.display = 'none';
                const sessionsInput = sessionsPerFrequencyField.querySelector('input');
                if (sessionsInput) {
                    sessionsInput.required = false;
                    sessionsInput.disabled = false;
                    sessionsInput.value = '';
                }
                updateLockedFieldPresentation(
                    sessionsPerFrequency,
                    sessionsPerFrequencyLock,
                    sessionsPerFrequencyStatus,
                    false,
                    ''
                );
                updateHiddenField(sessionsPerFrequencyHidden, '', false);
            }
            if (calculatedMinutesField) {
                calculatedMinutesField.style.display = 'none';
                if (calculatedMinutes) {
                    calculatedMinutes.disabled = false;
                    calculatedMinutes.value = '';
                }
                updateLockedFieldPresentation(
                    calculatedMinutes,
                    calculatedMinutesLock,
                    calculatedMinutesStatus,
                    false,
                    ''
                );
                updateHiddenField(calculatedMinutesHidden, '', false);
            }
            if (adjustedMinutesField) {
                adjustedMinutesField.style.display = 'none';
                if (adjustedMinutes) {
                    adjustedMinutes.value = '';
                }
            }
            if (thoCalculationHint) {
                thoCalculationHint.textContent = 'Total Hours Own by therapist';
            }
        }
    }

    // Fetch and update therapist dropdown based on selected primary service
    async function fetchTherapistsForServices() {
        if (!assignedTherapistId || !therapistsForServiceUrl) {
            return;
        }

        const $therapist = $(assignedTherapistId);
        const selectedValue = $therapist.val();
        const serviceIds = primaryServiceId?.value ? [primaryServiceId.value] : [];

        try {
            const params = new URLSearchParams();
            serviceIds.forEach((id) => params.append('service_ids[]', id));
            if (currentAssignedTherapistId) {
                params.append('include_therapist_id', currentAssignedTherapistId);
            }
            const url = params.toString()
                ? `${therapistsForServiceUrl}?${params.toString()}`
                : therapistsForServiceUrl;

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            if (!response.ok) {
                return;
            }

            const therapists = await response.json();

            // Destroy Select2 before modifying options
            if ($therapist.data('select-initialized') && typeof $therapist.select2 === 'function') {
                $therapist.select2('destroy');
                $therapist.data('select-initialized', false);
            }

            // Rebuild native options
            assignedTherapistId.innerHTML = '<option value="">Unassigned</option>';
            therapists.forEach((therapist) => {
                const option = document.createElement('option');
                option.value = therapist.id;
                option.textContent = therapist.name;
                if (String(therapist.id) === String(selectedValue)) {
                    option.selected = true;
                }
                assignedTherapistId.appendChild(option);
            });

            // Reinitialize Select2 via the global helper
            if (typeof window.initSelectBoxes === 'function') {
                await window.initSelectBoxes();
            }
        } catch {
            // Silently fail - therapist dropdown keeps its current options
        }
    }

    // Add event listeners
    if (primaryServiceId) {
        bindSelectListeners(primaryServiceId, () => {
            refreshSchedulingState({ toggleVisibility: true });
            fetchTherapistsForServices();
        });
    }

    [minutesPerSession, sessionsPerFrequency].forEach((field) => {
        bindInputListeners(field, () => {
            refreshSchedulingState();
        });
    });

    bindSelectListeners(frequency, () => {
        refreshSchedulingState();
    });

    [startDate, endDate].forEach((field) => {
        bindInputListeners(field, () => {
            refreshSchedulingState();
        });
    });

    if (adjustedMinutes) {
        bindInputListeners(adjustedMinutes, refreshSchedulingState);
    }

    // Initial setup - check current service if editing
    if (currentServiceData && primaryServiceId) {
        primaryServiceId.value = currentServiceData.id;
    }
    toggleFrequencyFields();
    refreshSchedulingState();

    // Filter therapists on initial load if a service is already selected
    if (primaryServiceId?.value) {
        fetchTherapistsForServices();
    }

    // "Add More SSA" button sets hidden flag before form submit
    $('#add-more-ssa-btn').on('click', function () {
        $('#add_more_ssa').val('1');
    });
});

