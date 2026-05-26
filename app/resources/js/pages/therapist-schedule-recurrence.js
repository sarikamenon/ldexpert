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
    const $warningBanner = $('#recurrence_change_warning');

    if (!$recurrenceTypeSelect.length || !$recurrenceEndDateContainer.length || !$occurrenceDatesContainer.length) {
        return;
    }

    const allowWeekendScheduling = $form.attr('data-allow-weekend-scheduling') === '1';

    const RECURRENCE_TYPE_NONE = 'none';
    const RECURRENCE_TYPE_CUSTOM_WEEKLY = 'custom_weekly';

    // Edit mode: read original values from the warning banner's data attributes.
    const isEditMode = $warningBanner.length > 0;
    const originalRecurrenceType = $warningBanner.data('original-recurrence-type') ?? null;
    const originalRecurrenceEndDate = $warningBanner.data('original-recurrence-end-date') ?? null;

    /**
     * Show or hide the recurrence change warning banner (edit mode only).
     */
    function updateWarningBanner() {
        if (!isEditMode) {
            return;
        }

        const currentType = getRecurrenceType();
        const currentEndDate = $recurrenceEndDateInput.val() || '';

        const typeChanged = currentType !== originalRecurrenceType;
        const endDateChanged = currentEndDate !== originalRecurrenceEndDate;

        const selectedDaysChanged = currentType === RECURRENCE_TYPE_CUSTOM_WEEKLY
            && isEditMode;

        if (typeChanged || endDateChanged || selectedDaysChanged) {
            $warningBanner.removeClass('hidden');
        } else {
            $warningBanner.addClass('hidden');
        }
    }
    const $weeklyDaysContainer = $('#weekly_days_container');
    const additionalDatesContainer = document.getElementById('additional_dates_container');
    const additionalDatesList = document.getElementById('additional_dates_list');
    const addAdditionalDateBtn = document.getElementById('add_additional_date_btn');

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
     * Format date for display (YYYY-MM-DD) using local timezone
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
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
     * Get selected weekday indices (0=Sun…6=Sat) from the day checkboxes
     */
    function getSelectedWeekdayIndices() {
        const dayMap = {
            sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6,
        };
        const indices = [];
        document.querySelectorAll('.weekly-day-checkbox:checked').forEach(function (checkbox) {
            const val = checkbox.value;
            if (dayMap[val] !== undefined) {
                indices.push(dayMap[val]);
            }
        });
        return indices;
    }

    /**
     * Generate occurrence dates for custom_weekly: every selected weekday between start and end
     */
    function generateCustomWeeklyDates(startDateStr, endDateStr) {
        const selectedDays = getSelectedWeekdayIndices();
        if (!startDateStr || !endDateStr || selectedDays.length === 0) {
            return [];
        }

        const dates = [];
        const endDate = new Date(endDateStr + 'T00:00:00');
        let current = new Date(startDateStr + 'T00:00:00');

        while (current <= endDate) {
            if (selectedDays.includes(current.getDay())) {
                dates.push(new Date(current));
            }
            current.setDate(current.getDate() + 1);
        }

        return dates;
    }

    /**
     * Generate all occurrence dates based on recurrence type, start date, and end date
     */
    function generateOccurrenceDates(startDateStr, endDateStr, recurrenceType) {
        if (!startDateStr || !endDateStr || recurrenceType === RECURRENCE_TYPE_NONE) {
            return [];
        }

        if (recurrenceType === RECURRENCE_TYPE_CUSTOM_WEEKLY) {
            return generateCustomWeeklyDates(startDateStr, endDateStr);
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
            $recurrenceEndDateInput[0]?.focus();
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
     * Update the occurrence counter text
     */
    function updateOccurrenceCounter() {
        const $counter = $occurrenceDatesContainer.find('.occurrence-counter');
        const generated = $occurrenceDatesContainer.find('.occurrence-date-row').length;
        const additional = additionalDatesList
            ? additionalDatesList.querySelectorAll('.additional-date-row').length
            : 0;
        const count = generated + additional;
        $counter.text(count + (count === 1 ? ' session' : ' sessions') + ' scheduled');
    }

    /**
     * Re-index occurrence labels after removal
     */
    function reIndexOccurrenceLabels() {
        $occurrenceDatesContainer.find('.occurrence-date-row').each(function (i) {
            $(this).find('.occurrence-label').text(i === 0 ? 'Start Date:' : 'Occurrence ' + (i + 1) + ':');
        });
    }

    /**
     * Render occurrence dates as editable inputs with remove buttons
     */
    function renderOccurrenceDates(dates) {
        $occurrenceDatesContainer.empty();

        if (dates.length === 0) {
            return;
        }

        // Counter showing total sessions
        const $counter = $('<p class="occurrence-counter text-sm font-medium text-primary mb-2"></p>');
        $occurrenceDatesContainer.append($counter);

        const $list = $('<div class="space-y-3"></div>');
        
        dates.forEach((date, index) => {
            const dateStr = formatDate(date);
            const isWeekendDate = isWeekend(dateStr);
            const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
            
            const $item = $('<div class="flex items-start gap-3 occurrence-date-row"></div>');
            const $label = $('<label class="occurrence-label block w-32 text-sm text-foreground/70 pt-2">' + 
                (index === 0 ? 'Start Date:' : 'Occurrence ' + (index + 1) + ':') + 
                '</label>');
            
            const $inputGroup = $('<div class="flex-1"></div>');
            const $inputRow = $('<div class="flex items-center gap-2"></div>');
            const $input = $('<input>')
                .attr('type', 'date')
                .attr('name', 'occurrence_dates[]')
                .attr('class', 'mt-1 block w-full border border-border rounded-lg px-3 py-2 text-sm occurrence-date-input')
                .attr('data-index', index)
                .attr('value', dateStr)
                .attr('min', $scheduleDateInput.val() || '');
            
            if (isWeekendDate && !allowWeekendScheduling) {
                $input.addClass('border-warning bg-warning/10');
            }

            $inputRow.append($input);

            // Add remove button for all occurrences except the first (start date)
            if (index > 0) {
                const $removeBtn = $('<button type="button" class="occurrence-remove-btn mt-1 p-2 text-danger/60 hover:text-danger hover:bg-danger/10 rounded-lg transition-colors" title="Remove this occurrence">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />' +
                    '</svg>' +
                    '</button>');
                $inputRow.append($removeBtn);
            }
            
            const $errorDiv = $('<div class="occurrence-error text-xs text-danger mt-1"></div>');
            
            $inputGroup.append($inputRow);
            if (isWeekendDate && !allowWeekendScheduling) {
                $inputGroup.append($('<p class="text-xs text-warning mt-1">⚠️ ' + dayName + ' (Weekend)</p>'));
            }
            $inputGroup.append($errorDiv);
            
            $item.append($label).append($inputGroup);
            $list.append($item);
        });

        $occurrenceDatesContainer.append($list);
        $occurrenceDatesContainer.removeClass('hidden');
        updateOccurrenceCounter();
    }

    /**
     * Toggle visibility of end date and occurrence dates fields based on recurrence type
     */
    function toggleRecurrenceFields() {
        const recurrenceType = getRecurrenceType();
        const isCustomWeekly = recurrenceType === RECURRENCE_TYPE_CUSTOM_WEEKLY;

        if ($weeklyDaysContainer.length) {
            if (isCustomWeekly) {
                $weeklyDaysContainer.removeClass('hidden');
            } else {
                $weeklyDaysContainer.addClass('hidden');
                // Reset all day selections when switching away from custom_weekly
                document.querySelectorAll('.weekly-day-checkbox').forEach(function (checkbox) {
                    checkbox.checked = false;
                    setDaySelected(checkbox.closest('label'), false);
                });
            }
        }

        // Additional one-off dates only apply to custom_weekly; clear them otherwise
        // so stale rows are not submitted under a different recurrence type.
        if (additionalDatesContainer) {
            if (isCustomWeekly) {
                additionalDatesContainer.classList.remove('hidden');
            } else {
                additionalDatesContainer.classList.add('hidden');
                if (additionalDatesList) {
                    additionalDatesList.innerHTML = '';
                }
            }
        }

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
        const inputs = document.querySelectorAll('.occurrence-date-input');

        // Count how often each date appears across generated and additional rows
        // so a one-off that collides with the weekly pattern is flagged client-side
        // (the server rejects the whole submit otherwise).
        const dateCounts = {};
        inputs.forEach(function (input) {
            const dateStr = input.value;
            if (dateStr) {
                dateCounts[dateStr] = (dateCounts[dateStr] || 0) + 1;
            }
        });

        inputs.forEach(function (input) {
            const dateStr = input.value;
            const row = input.closest('.occurrence-date-row, .additional-date-row');
            const errorDiv = row ? row.querySelector('.occurrence-error') : null;
            if (errorDiv) {
                errorDiv.textContent = '';
            }
            input.classList.remove('border-danger', 'border-warning', 'bg-warning/10');

            if (!dateStr) {
                return;
            }

            // Duplicate of another occurrence — server enforces unique dates.
            if (dateCounts[dateStr] > 1) {
                if (errorDiv) {
                    errorDiv.textContent = 'This date is already scheduled. Each session must be on a unique date.';
                }
                input.classList.add('border-danger');
                return;
            }

            // Check for weekend
            if (isWeekend(dateStr) && !allowWeekendScheduling) {
                const date = new Date(dateStr + 'T00:00:00');
                const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
                if (errorDiv) {
                    errorDiv.textContent = '⚠️ ' + dayName + ' is a weekend. Please adjust the date.';
                }
                input.classList.add('border-warning', 'bg-warning/10');
            }
        });
    }

    // Initialize: Check initial recurrence type value
    toggleRecurrenceFields();

    // Listen to recurrence type changes
    $recurrenceTypeSelect.on('change', function () {
        toggleRecurrenceFields();
        updateWarningBanner();
    });

    // If Select2 is available, also listen to its change event
    if ($recurrenceTypeSelect.data('select2')) {
        $recurrenceTypeSelect.on('select2:select select2:change', function () {
            toggleRecurrenceFields();
            updateWarningBanner();
        });
    }

    /**
     * Apply or remove the selected state on a day pill label
     */
    function setDaySelected(label, selected) {
        const check = label.querySelector('.weekly-day-check');
        if (selected) {
            label.classList.add('is-selected');
            if (check) {
                check.classList.replace('w-0', 'w-3.5');
                check.classList.replace('opacity-0', 'opacity-100');
            }
        } else {
            label.classList.remove('is-selected');
            if (check) {
                check.classList.replace('w-3.5', 'w-0');
                check.classList.replace('opacity-100', 'opacity-0');
            }
        }
    }

    // Init selected state for any pre-checked boxes (e.g. old() after validation failure)
    document.querySelectorAll('.weekly-day-checkbox:checked').forEach(function (checkbox) {
        setDaySelected(checkbox.closest('label'), true);
    });

    // Toggle selected state and regenerate occurrences when day checkboxes change
    document.querySelectorAll('.weekly-day-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            setDaySelected(this.closest('label'), this.checked);
            if (getRecurrenceType() === RECURRENCE_TYPE_CUSTOM_WEEKLY) {
                updateOccurrenceDates();
            }
            updateWarningBanner();
        });
    });

    // -------------------------------------------------------------------------
    // Additional one-off dates (custom weekly)
    // Extra sessions on days outside the weekly pattern. Rendered as date inputs
    // named occurrence_dates[] so they merge with the generated dates on submit
    // and inherit the parent session's start time and duration on the backend.
    // -------------------------------------------------------------------------

    /**
     * Re-number the "Additional N:" labels after a row is added or removed.
     */
    function reindexAdditionalDateLabels() {
        if (!additionalDatesList) {
            return;
        }
        additionalDatesList.querySelectorAll('.additional-date-row').forEach(function (row, index) {
            const label = row.querySelector('.additional-date-label');
            if (label) {
                label.textContent = 'Additional ' + (index + 1) + ':';
            }
        });
    }

    /**
     * Append an empty additional-date input row.
     */
    function addAdditionalDateRow() {
        if (!additionalDatesList) {
            return;
        }

        const scheduleDate = $scheduleDateInput.val() || '';
        const count = additionalDatesList.querySelectorAll('.additional-date-row').length;

        const row = document.createElement('div');
        row.className = 'flex items-start gap-3 additional-date-row';

        const label = document.createElement('label');
        label.className = 'additional-date-label block w-32 text-sm text-foreground/70 pt-2';
        label.textContent = 'Additional ' + (count + 1) + ':';

        const group = document.createElement('div');
        group.className = 'flex-1';

        const inputRow = document.createElement('div');
        inputRow.className = 'flex items-center gap-2';

        const input = document.createElement('input');
        input.type = 'date';
        input.name = 'occurrence_dates[]';
        input.className = 'mt-1 block w-full border border-border rounded-lg px-3 py-2 text-sm occurrence-date-input additional-date-input';
        if (scheduleDate) {
            input.min = scheduleDate;
        }

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'additional-date-remove-btn mt-1 p-2 text-danger/60 hover:text-danger hover:bg-danger/10 rounded-lg transition-colors';
        removeBtn.title = 'Remove this date';
        removeBtn.setAttribute('aria-label', 'Remove this additional date');
        removeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>';

        const errorDiv = document.createElement('div');
        errorDiv.className = 'occurrence-error text-xs text-danger mt-1';

        // Re-validate (weekend + duplicate) and refresh the session counter as the date changes.
        input.addEventListener('change', validateAllOccurrenceDates);

        inputRow.appendChild(input);
        inputRow.appendChild(removeBtn);
        group.appendChild(inputRow);
        group.appendChild(errorDiv);
        row.appendChild(label);
        row.appendChild(group);
        additionalDatesList.appendChild(row);

        updateOccurrenceCounter();
        input.focus();
    }

    if (addAdditionalDateBtn) {
        addAdditionalDateBtn.addEventListener('click', addAdditionalDateRow);
    }

    if (additionalDatesList) {
        additionalDatesList.addEventListener('click', function (event) {
            const btn = event.target.closest('.additional-date-remove-btn');
            if (!btn) {
                return;
            }
            const row = btn.closest('.additional-date-row');
            if (row) {
                row.remove();
                reindexAdditionalDateLabels();
                updateOccurrenceCounter();
                validateAllOccurrenceDates();
            }
        });
    }

    // Update min date when schedule date changes
    $scheduleDateInput.on('change', function() {
        updateEndDateMinDate();
        updateOccurrenceDates();

        // Keep already-added additional dates constrained to the new start date.
        const scheduleDate = $scheduleDateInput.val() || '';
        if (scheduleDate) {
            document.querySelectorAll('.additional-date-input').forEach(function (input) {
                input.min = scheduleDate;
            });
        }
    });

    // Update occurrence dates and warning when end date changes
    $recurrenceEndDateInput.on('change', function() {
        if (validateEndDate()) {
            updateOccurrenceDates();
        }
        updateWarningBanner();
    });

    // Validate individual occurrence date changes
    $(document).on('change', '.occurrence-date-input', function() {
        validateAllOccurrenceDates();
    });

    // Handle removal of occurrence dates
    $(document).on('click', '.occurrence-remove-btn', function() {
        const $row = $(this).closest('.occurrence-date-row');
        $row.fadeOut(200, function() {
            $row.remove();
            reIndexOccurrenceLabels();
            updateOccurrenceCounter();
            validateAllOccurrenceDates();
        });
    });

    // Validate on form submit
    $form.on('submit', function(event) {
        const recurrenceType = getRecurrenceType();

        // Only validate end date if recurrence type is not "none"
        if (recurrenceType && recurrenceType !== RECURRENCE_TYPE_NONE) {
            // For custom_weekly, require at least one day selected
            if (recurrenceType === RECURRENCE_TYPE_CUSTOM_WEEKLY && getSelectedWeekdayIndices().length === 0) {
                event.preventDefault();
                errorAlert('Please select at least one day of the week for the custom schedule.');
                return false;
            }

            if (!$recurrenceEndDateInput.val()) {
                event.preventDefault();
                errorAlert('End date is required for recurring schedules.');
                $recurrenceEndDateInput[0]?.focus();
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

            if (hasWeekend && !allowWeekendScheduling) {
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
                emptyDates[0]?.focus();
                return false;
            }
        }
    });
});
