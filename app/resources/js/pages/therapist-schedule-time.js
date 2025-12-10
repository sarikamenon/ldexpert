import { errorAlert } from '../common/sweetalert';

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const startTimeInput = document.getElementById('start_time');
        const durationSelect = document.getElementById('duration_minutes');
        const endTimeDisplay = document.getElementById('end_time_display');
        const form = document.getElementById('scheduleCreateForm') || document.getElementById('scheduleEditForm');

        if (!startTimeInput || !durationSelect || !endTimeDisplay) {
            return;
        }

        const updateEndTime = () => {
            const startTime = startTimeInput.value;
            // Get value from Select2 if available, otherwise use native value
            let durationValue;
            if (window.jQuery && window.jQuery(durationSelect).data('select2')) {
                durationValue = parseInt(window.jQuery(durationSelect).val(), 10);
            } else {
                durationValue = parseInt(durationSelect.value, 10);
            }
            
            const computed = computeEndTime(startTime, durationValue);

            if (!computed) {
                endTimeDisplay.value = '';
                endTimeDisplay.dataset.nextDay = '0';
                return;
            }

            endTimeDisplay.value = computed.label;
            endTimeDisplay.dataset.nextDay = computed.crossesDay ? '1' : '0';
        };

        startTimeInput.addEventListener('change', updateEndTime);
        
        // Listen to both native change and Select2 change events
        durationSelect.addEventListener('change', updateEndTime);
        
        // If Select2 is available, also listen to its change event
        if (window.jQuery) {
            window.jQuery(durationSelect).on('select2:select select2:change', updateEndTime);
        }
        
        // Initial calculation - wait a bit for Select2 to initialize
        setTimeout(updateEndTime, 100);

        if (form) {
            form.addEventListener('submit', (event) => {
                // Get value from Select2 if available, otherwise use native value
                let durationValue;
                if (window.jQuery && window.jQuery(durationSelect).data('select2')) {
                    durationValue = window.jQuery(durationSelect).val();
                } else {
                    durationValue = durationSelect.value;
                }
                
                if (!startTimeInput.value || !durationValue) {
                    event.preventDefault();
                    errorAlert('Start time and duration are required.');
                    return;
                }
            });
        }
    });

    function computeEndTime(startTime, durationMinutes) {
        if (!startTime || !Number.isFinite(durationMinutes)) {
            return null;
        }

        const [hours, minutes] = startTime.split(':').map(Number);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return null;
        }

        const start = new Date();
        start.setHours(hours, minutes, 0, 0);

        const end = new Date(start.getTime() + durationMinutes * 60 * 1000);
        const crossesDay = end.getDate() !== start.getDate();

        const endHours = String(end.getHours()).padStart(2, '0');
        const endMinutes = String(end.getMinutes()).padStart(2, '0');
        const time = `${endHours}:${endMinutes}`;
        const label = crossesDay ? `${time} (next day)` : time;

        return {
            time,
            label,
            crossesDay,
        };
    }
})();

