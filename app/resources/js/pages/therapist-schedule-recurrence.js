import $ from 'jquery';
import { errorAlert } from '../common/sweetalert';

$(function () {
    'use strict';

    const $recurrenceTypeSelect = $('#recurrence_type');
    const $recurrenceEndDateContainer = $('#recurrence_end_date_container');
    const $recurrenceEndDateInput = $('#recurrence_end_date');
    const $occurrenceDatesContainer = $('#occurrence_dates_container');
    const $scheduleDateInput = $('#schedule_date');
    const $form = $('#scheduleCreateForm, #scheduleEditForm');

    // Only proceed if we're on the create form (recurrence options are only shown there)
    if (!$recurrenceTypeSelect.length || !$recurrenceEndDateContainer.length || !$occurrenceDatesContainer.length) {
        return;
    }

    const RECURRENCE_TYPE_NONE = 'none';

    /**
     * Get recurrence type value (handles Select2)
     */
    function getRecurrenceType() {
        if ($recurrenceTypeSelect.data('select2')) {
            return $recurrenceTypeSelect.val();
        }
        return $recurrenceTypeSelect.val();
    }

    /**
     * Check if a date is a weekend (Saturday or Sunday)
     */
    function isWeekend(dateStr) {
        if (!dateStr) return false;
        const date = new Date(dateStr + 'T00:00:00');
        const dayOfWeek = date.getDay();
        return dayOfWeek === 0 || dayOfWeek === 6; // 0 = Sunday, 6 = Saturday
    }

    /**
     * Format date for display (YYYY-MM-DD)
     */
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    /**
     * Calculate next occurrence date based on recurrence type
     */
    function getNextRecurrenceDate(currentDate, recurrenceType) {
        const date = new Date(currentDate);
        switch (recurrenceType) {
            case 'daily':
                date.setDate(date.getDate() + 1);
                break;
            case 'weekly':
                date.setDate(date.getDate() + 7);
                break;
            case 'bi_weekly':
                date.setDate(date.getDate() + 14);
                break;
            case 'monthly':
                date.setMonth(date.getMonth() + 1);
                break;
        }
        return date;
    }

    /**
     * Generate all occurrence dates based on recurrence type, start date, and end date
     */
    function generateOccurrenceDates(startDateStr, endDateStr, recurrenceType) {
        if (!startDateStr || !endDateStr || recurrenceType === RECURRENCE_TYPE_NONE) {
            return [];
        }

        const dates = [];
        let currentDate = new Date(startDateStr + 'T00:00:00');
        const endDate = new Date(endDateStr + 'T00:00:00');

        // First occurrence is the start date
        dates.push(new Date(currentDate));

        // Generate subsequent occurrences until end date
        while (currentDate <= endDate) {
            currentDate = getNextRecurrenceDate(currentDate, recurrenceType);
            if (currentDate <= endDate) {
                dates.push(new Date(currentDate));
            }
        }

        return dates;
    }

    /**
     * Validate that end date is after schedule date
     */
    function validateEndDate() {
        const scheduleDate = $scheduleDateInput.val();
        const endDate = $recurrenceEndDateInput.val();

        if (!scheduleDate || !endDate) {
            return true; // Validation will be handled by backend
        }

        const schedule = new Date(scheduleDate + 'T00:00:00');
        const end = new Date(endDate + 'T00:00:00');

        if (end <= schedule) {
            errorAlert('End date must be after the schedule start date.');
            $recurrenceEndDateInput.focus();
            return false;
        }

        return true;
    }

    /**
     * Update the min date attribute of end date input to match schedule date
     */
    function updateEndDateMinDate() {
        const scheduleDate = $scheduleDateInput.val();
        if (scheduleDate) {
            const schedule = new Date(scheduleDate);
            schedule.setDate(schedule.getDate() + 1);
            const minDateStr = formatDate(schedule);
            $recurrenceEndDateInput.attr('min', minDateStr);
        }
    }

    /**
     * Render occurrence dates as editable inputs
     */
    function renderOccurrenceDates(dates) {
        $occurrenceDatesContainer.empty();

        if (dates.length === 0) {
            return;
        }

        const $list = $('<div class="space-y-3"></div>');
        
        dates.forEach((date, index) => {
            const dateStr = formatDate(date);
            const isWeekendDate = isWeekend(dateStr);
            const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
            
            const $item = $('<div class="flex items-start gap-3"></div>');
            const $label = $('<label class="block w-32 text-sm text-foreground/70 pt-2">' + 
                (index === 0 ? 'Start Date:' : 'Occurrence ' + (index + 1) + ':') + 
                '</label>');
            
            const $inputGroup = $('<div class="flex-1"></div>');
            const $input = $('<input>')
                .attr('type', 'date')
                .attr('name', 'occurrence_dates[]')
                .attr('class', 'mt-1 block w-full border border-border rounded-lg px-3 py-2 text-sm occurrence-date-input')
                .attr('data-index', index)
                .attr('value', dateStr)
                .attr('min', $scheduleDateInput.val() || '');
            
            if (isWeekendDate) {
                $input.addClass('border-warning bg-warning/10');
            }
            
            const $errorDiv = $('<div class="occurrence-error text-xs text-danger mt-1"></div>');
            
            $inputGroup.append($input);
            if (isWeekendDate) {
                $inputGroup.append($('<p class="text-xs text-warning mt-1">⚠️ ' + dayName + ' (Weekend)</p>'));
            }
            $inputGroup.append($errorDiv);
            
            $item.append($label).append($inputGroup);
            $list.append($item);
        });

        $occurrenceDatesContainer.append($list);
        $occurrenceDatesContainer.removeClass('hidden');
    }

    /**
     * Toggle visibility of end date and occurrence dates fields based on recurrence type
     */
    function toggleRecurrenceFields() {
        const recurrenceType = getRecurrenceType();

        if (recurrenceType && recurrenceType !== RECURRENCE_TYPE_NONE) {
            $recurrenceEndDateContainer.removeClass('hidden');
            $recurrenceEndDateInput.attr('required', 'required');
            updateEndDateMinDate();
            updateOccurrenceDates();
        } else {
            $recurrenceEndDateContainer.addClass('hidden');
            $occurrenceDatesContainer.addClass('hidden').empty();
            $recurrenceEndDateInput.removeAttr('required').val('');
        }
    }

    /**
     * Update occurrence dates when recurrence type, schedule date, or end date changes
     */
    function updateOccurrenceDates() {
        const recurrenceType = getRecurrenceType();
        const scheduleDate = $scheduleDateInput.val();
        const endDate = $recurrenceEndDateInput.val();

        if (recurrenceType === RECURRENCE_TYPE_NONE || !scheduleDate || !endDate) {
            $occurrenceDatesContainer.addClass('hidden').empty();
            return;
        }

        const dates = generateOccurrenceDates(scheduleDate, endDate, recurrenceType);
        renderOccurrenceDates(dates);
        
        // Validate after rendering
        validateAllOccurrenceDates();
    }

    /**
     * Validate all occurrence dates for weekends and overlaps
     */
    function validateAllOccurrenceDates() {
        $('.occurrence-date-input').each(function() {
            const $input = $(this);
            const dateStr = $input.val();
            const $errorDiv = $input.siblings('.occurrence-error');
            $errorDiv.empty();
            $input.removeClass('border-danger border-warning bg-warning/10');

            if (!dateStr) {
                return;
            }

            // Check for weekend
            if (isWeekend(dateStr)) {
                const date = new Date(dateStr + 'T00:00:00');
                const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
                $errorDiv.text('⚠️ ' + dayName + ' is a weekend. Please adjust the date.');
                $input.addClass('border-warning bg-warning/10');
            }
        });
    }

    /**
     * Check for overlapping schedules (client-side basic validation)
     * Note: Full overlap validation will be done on the backend
     */
    function checkOverlaps() {
        // This would require an API call to check existing schedules
        // For now, we'll rely on backend validation
        // But we can add visual indicators here if needed
    }

    // Initialize: Check initial recurrence type value
    toggleRecurrenceFields();

    // Listen to recurrence type changes
    $recurrenceTypeSelect.on('change', toggleRecurrenceFields);
    
    // If Select2 is available, also listen to its change event
    if ($recurrenceTypeSelect.data('select2')) {
        $recurrenceTypeSelect.on('select2:select select2:change', toggleRecurrenceFields);
    }

    // Update min date when schedule date changes
    $scheduleDateInput.on('change', function() {
        updateEndDateMinDate();
        updateOccurrenceDates();
    });

    // Update occurrence dates when end date changes
    $recurrenceEndDateInput.on('change', function() {
        if (validateEndDate()) {
            updateOccurrenceDates();
        }
    });

    // Validate individual occurrence date changes
    $(document).on('change', '.occurrence-date-input', function() {
        validateAllOccurrenceDates();
    });

    // Validate on form submit
    $form.on('submit', function(event) {
        const recurrenceType = getRecurrenceType();

        // Only validate end date if recurrence type is not "none"
        if (recurrenceType && recurrenceType !== RECURRENCE_TYPE_NONE) {
            if (!$recurrenceEndDateInput.val()) {
                event.preventDefault();
                errorAlert('End date is required for recurring schedules.');
                $recurrenceEndDateInput.focus();
                return false;
            }

            if (!validateEndDate()) {
                event.preventDefault();
                return false;
            }

            // Check if any occurrence dates are weekends
            let hasWeekend = false;
            $('.occurrence-date-input').each(function() {
                const dateStr = $(this).val();
                if (dateStr && isWeekend(dateStr)) {
                    hasWeekend = true;
                    return false; // break loop
                }
            });

            if (hasWeekend) {
                event.preventDefault();
                errorAlert('One or more occurrence dates fall on a weekend. Please adjust those dates before submitting.');
                return false;
            }

            // Ensure all occurrence dates are filled
            const emptyDates = $('.occurrence-date-input').filter(function() {
                return !$(this).val();
            });

            if (emptyDates.length > 0) {
                event.preventDefault();
                errorAlert('Please fill in all occurrence dates.');
                emptyDates.first().focus();
                return false;
            }
        }
    });
});
