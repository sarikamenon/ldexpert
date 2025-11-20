/**
 * SSA Form JavaScript
 * Handles THO minutes auto-calculation, calculated minutes, and conditional field display
 */

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
    const minutesPerSession = document.getElementById('minutes_per_session');
    const frequency = document.getElementById('frequency');
    const sessionsPerFrequency = document.getElementById('sessions_per_frequency');
    const calculatedMinutes = document.getElementById('calculated_minutes');
    const adjustedMinutes = document.getElementById('adjusted_minutes');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const thoMinutes = document.getElementById('tho_minutes');
    const thoCalculationHint = document.getElementById('tho-calculation-hint');

    // Field containers for conditional display
    const frequencyFields = document.getElementById('frequency-fields');
    const frequencyField = document.getElementById('frequency-field');
    const sessionsPerFrequencyField = document.getElementById('sessions-per-frequency-field');
    const calculatedMinutesField = document.getElementById('calculated-minutes-field');
    const adjustedMinutesField = document.getElementById('adjusted-minutes-field');

    // Check if service supports frequency and toggle fields
    function toggleFrequencyFields() {
        if (!primaryServiceId?.value) {
            return;
        }

        const serviceId = parseInt(primaryServiceId.value, 10);
        const supportsFrequency = servicesData[serviceId] === true || servicesData[serviceId] === 1;

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
        if (!minutesPerSession?.value || !sessionsPerFrequency?.value) {
            if (calculatedMinutes) {
                calculatedMinutes.value = '';
            }
            return;
        }

        const mins = parseInt(minutesPerSession.value, 10);
        const sessions = parseInt(sessionsPerFrequency.value, 10);

        if (calculatedMinutes && mins > 0 && sessions > 0) {
            calculatedMinutes.value = mins * sessions;
        }
    }

    // Calculate THO minutes
    function calculateThoMinutes() {
        const serviceId = primaryServiceId?.value ? parseInt(primaryServiceId.value, 10) : null;
        const supportsFrequency = serviceId && (servicesData[serviceId] === true || servicesData[serviceId] === 1);

        if (supportsFrequency) {
            // For frequency-based services, use the existing calculation
            if (!minutesPerSession?.value || !frequency?.value || !sessionsPerFrequency?.value || !startDate?.value || !endDate?.value) {
                return;
            }

            const mins = parseInt(minutesPerSession.value, 10);
            const freq = frequency.value;
            const sessions = parseInt(sessionsPerFrequency.value, 10);
            const start = new Date(startDate.value);
            const end = new Date(endDate.value);

            if (start >= end) {
                return;
            }

            // Calculate days difference
            const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

            // Frequency multipliers (per year)
            const frequencyMultipliers = {
                weekly: 52 / 365,
                bi_weekly: 26 / 365,
                monthly: 12 / 365,
                quarterly: 4 / 365,
            };

            const multiplier = frequencyMultipliers[freq] || 0;
            const numberOfFrequencies = Math.ceil(daysDiff * multiplier);
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

    // Add event listeners
    if (primaryServiceId) {
        primaryServiceId.addEventListener('change', () => {
            toggleFrequencyFields();
            calculateThoMinutes();
        });
    }

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

    [frequency, startDate, endDate, adjustedMinutes].forEach((field) => {
        if (field) {
            field.addEventListener('change', calculateThoMinutes);
            field.addEventListener('input', calculateThoMinutes);
        }
    });

    // Initial setup - check current service if editing
    if (currentServiceData && primaryServiceId) {
        primaryServiceId.value = currentServiceData.id;
    }
    toggleFrequencyFields();
    calculateCalculatedMinutes();
    calculateThoMinutes();
});

