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
            const durationValue = parseInt(durationSelect.value, 10);
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
        durationSelect.addEventListener('change', updateEndTime);
        updateEndTime();

        if (form) {
            form.addEventListener('submit', (event) => {
                if (!startTimeInput.value || !durationSelect.value) {
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

