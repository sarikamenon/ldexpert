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
    const additionalServiceIds = document.getElementById('additional_service_ids');
    const assignedTherapistId = document.getElementById('assigned_therapist_id');
    const minutesPerSession = document.getElementById('minutes_per_session');
    const frequency = document.getElementById('frequency');
    const sessionsPerFrequency = document.getElementById('sessions_per_frequency');
    const calculatedMinutes = document.getElementById('calculated_minutes');
    const adjustedMinutes = document.getElementById('adjusted_minutes');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const thoMinutes = document.getElementById('tho_minutes');
    const thoCalculationHint = document.getElementById('tho-calculation-hint');

    // Therapist filtering by service
    const therapistsForServiceUrlEl = document.getElementById('therapists-for-service-url');
    const therapistsForServiceUrl = therapistsForServiceUrlEl ? JSON.parse(therapistsForServiceUrlEl.textContent) : null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Field containers for conditional display
    const frequencyFields = document.getElementById('frequency-fields');
    const frequencyField = document.getElementById('frequency-field');
    const sessionsPerFrequencyField = document.getElementById('sessions-per-frequency-field');
    const calculatedMinutesField = document.getElementById('calculated-minutes-field');
    const adjustedMinutesField = document.getElementById('adjusted-minutes-field');

    const frequencyMultipliers = {
        weekly: 52 / 365,
        bi_weekly: 26 / 365,
        monthly: 12 / 365,
        quarterly: 4 / 365,
    };

    function supportsFrequencyBasedScheduling() {
        if (!primaryServiceId?.value) {
            return false;
        }

        const serviceId = parseInt(primaryServiceId.value, 10);
        return servicesData[serviceId] === true || servicesData[serviceId] === 1;
    }

    function getNumberOfFrequencies() {
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
                thoCalculationHint.textContent = 'Auto-calculated: Minutes per Session × (Sessions per Frequency × Number of Frequencies in Date Range)';
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
                    sessionsInput.value = '';
                }
            }
            if (calculatedMinutesField) {
                calculatedMinutesField.style.display = 'none';
                if (calculatedMinutes) {
                    calculatedMinutes.value = '';
                }
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

    // Calculate calculated minutes based on sessions per frequency
    function calculateCalculatedMinutes() {
        if (!supportsFrequencyBasedScheduling()) {
            if (calculatedMinutes) {
                calculatedMinutes.value = '';
            }
            return;
        }

        if (!minutesPerSession?.value || !sessionsPerFrequency?.value) {
            if (calculatedMinutes) {
                calculatedMinutes.value = '';
            }
            return;
        }

        const mins = parseInt(minutesPerSession.value, 10);
        const sessions = parseInt(sessionsPerFrequency.value, 10);
        const numberOfFrequencies = getNumberOfFrequencies();

        if (calculatedMinutes && mins > 0 && sessions > 0 && numberOfFrequencies) {
            const totalSessions = sessions * numberOfFrequencies;
            calculatedMinutes.value = totalSessions * mins;
        } else if (calculatedMinutes) {
            calculatedMinutes.value = '';
        }
    }

    // Calculate THO minutes
    function calculateThoMinutes() {
        const supportsFrequency = supportsFrequencyBasedScheduling();

        if (supportsFrequency) {
            // For frequency-based services, use the existing calculation
            if (!minutesPerSession?.value || !frequency?.value || !sessionsPerFrequency?.value || !startDate?.value || !endDate?.value) {
                return;
            }

            const mins = parseInt(minutesPerSession.value, 10);
            const sessions = parseInt(sessionsPerFrequency.value, 10);
            const numberOfFrequencies = getNumberOfFrequencies();

            if (!numberOfFrequencies) {
                return;
            }

            const totalSessions = numberOfFrequencies * sessions;
            let calculatedTho = totalSessions * mins;

            // Apply adjusted minutes if provided
            if (adjustedMinutes?.value) {
                const adjusted = parseInt(adjustedMinutes.value, 10);
                calculatedTho = calculatedTho + adjusted;
            }

            if (thoMinutes && calculatedTho > 0) {
                thoMinutes.value = calculatedTho;
            }
        } else {
            // For non-frequency services, THO minutes should be manually entered
            // No auto-calculation needed
        }
    }

    // Collect all selected service IDs (primary + additional)
    function getAllSelectedServiceIds() {
        const ids = [];
        if (primaryServiceId?.value) {
            ids.push(primaryServiceId.value);
        }
        if (additionalServiceIds) {
            const selected = $(additionalServiceIds).val();
            if (Array.isArray(selected)) {
                ids.push(...selected);
            }
        }
        return ids.filter((id) => id && id !== '');
    }

    // Fetch and update therapist dropdown based on all selected services
    async function fetchTherapistsForServices() {
        if (!assignedTherapistId || !therapistsForServiceUrl) {
            return;
        }

        const $therapist = $(assignedTherapistId);
        const selectedValue = $therapist.val();
        const serviceIds = getAllSelectedServiceIds();

        try {
            const params = new URLSearchParams();
            serviceIds.forEach((id) => params.append('service_ids[]', id));
            const url = serviceIds.length
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

    // Add event listeners — use jQuery .on('change') for Select2 compatibility
    if (primaryServiceId) {
        $(primaryServiceId).on('change', () => {
            toggleFrequencyFields();
            calculateCalculatedMinutes();
            calculateThoMinutes();
            fetchTherapistsForServices();
        });
    }

    if (additionalServiceIds) {
        $(additionalServiceIds).on('change', () => {
            fetchTherapistsForServices();
        });
    }

    // Native input fields — standard addEventListener is fine
    [minutesPerSession, sessionsPerFrequency].forEach((field) => {
        if (field) {
            field.addEventListener('change', () => {
                calculateCalculatedMinutes();
                calculateThoMinutes();
            });
            field.addEventListener('input', () => {
                calculateCalculatedMinutes();
                calculateThoMinutes();
            });
        }
    });

    // Frequency is a Select2 — use jQuery event
    if (frequency) {
        $(frequency).on('change', () => {
            calculateCalculatedMinutes();
            calculateThoMinutes();
        });
    }

    // Date inputs — native events
    [startDate, endDate].forEach((field) => {
        if (field) {
            field.addEventListener('change', () => {
                calculateCalculatedMinutes();
                calculateThoMinutes();
            });
            field.addEventListener('input', () => {
                calculateCalculatedMinutes();
                calculateThoMinutes();
            });
        }
    });

    if (adjustedMinutes) {
        adjustedMinutes.addEventListener('change', calculateThoMinutes);
        adjustedMinutes.addEventListener('input', calculateThoMinutes);
    }

    [startDate, endDate, minutesPerSession, sessionsPerFrequency].forEach((field) => {
        if (field) {
            field.addEventListener('blur', () => {
                calculateCalculatedMinutes();
                calculateThoMinutes();
            });
        }
    });

    // Initial setup - check current service if editing
    if (currentServiceData && primaryServiceId) {
        primaryServiceId.value = currentServiceData.id;
    }
    toggleFrequencyFields();
    calculateCalculatedMinutes();
    calculateThoMinutes();

    // Filter therapists on initial load if any service is already selected
    if (getAllSelectedServiceIds().length > 0) {
        fetchTherapistsForServices();
    }
});

