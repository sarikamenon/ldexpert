import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

(function ($) {
    'use strict';

    $(document).ready(function () {
        const $calendarEl = $('#calendar');
        const $eventList = $('#calendarEventsList');
        const $dateLabel = $('#calendarEventDateLabel');
        const $todayViewBtn = $('.calendar-today-btn');
        const $form = $('#schoolCalendarEventForm');
        const $resetBtn = $('#calendarEventReset');
        const $cancelBtn = $('#calendarEventCancel');
        const $submitBtn = $('#calendarEventSubmit');

        if (! $calendarEl.length || ! $eventList.length) {
            return;
        }

        const listUrl = $eventList.data('list-url');
        const updateUrlTemplate = $eventList.data('update-url-template');
        const deleteUrlTemplate = $eventList.data('delete-url-template');

        const selectedDateStr = $calendarEl.data('selected-date');
        let selectedDate = selectedDateStr
            ? new Date(`${selectedDateStr}T00:00:00`)
            : new Date();
        let currentMonth = selectedDate.getMonth();
        let currentYear = selectedDate.getFullYear();
        let cachedEvents = [];

        renderCalendar(currentYear, currentMonth, selectedDate);
        loadEventsForMonth(currentYear, currentMonth, selectedDate);

        $todayViewBtn.on('click', () => {
            const today = new Date();
            selectedDate = today;
            currentMonth = today.getMonth();
            currentYear = today.getFullYear();
            renderCalendar(currentYear, currentMonth, selectedDate);
            loadEventsForMonth(currentYear, currentMonth, selectedDate);
        });

        $resetBtn.on('click', () => resetForm());
        $cancelBtn.on('click', () => resetForm());

        $form.on('submit', function (e) {
            e.preventDefault();
            clearErrors();

            const payload = {
                title: $('#event_title').val(),
                event_type: $('#event_type').val(),
                start_date: $('#event_start_date').val(),
                end_date: $('#event_end_date').val(),
                notes: $('#event_notes').val(),
            };

            const eventId = $('#calendar_event_id').val();
            const url = eventId
                ? updateUrlTemplate.replace('EVENT_ID', eventId)
                : $form.data('store-url');
            const method = eventId ? 'PUT' : 'POST';

            $.ajax({
                url,
                method,
                data: payload,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                success(response) {
                    const event = response.event;
                    if (eventId) {
                        cachedEvents = cachedEvents.map((item) => (item.id === event.id ? event : item));
                        successToast('Calendar event updated.');
                    } else {
                        cachedEvents.push(event);
                        successToast('Calendar event added.');
                    }

                    renderCalendar(currentYear, currentMonth, selectedDate);
                    renderEventsForDate(selectedDate);
                    resetForm();
                },
                error(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        showErrors(xhr.responseJSON.errors);
                        return;
                    }
                    errorAlert('Unable to save the calendar event.');
                },
            });
        });

        $(document).on('click', '.js-calendar-event-edit', function () {
            const eventId = $(this).data('event-id');
            const event = cachedEvents.find((item) => item.id === eventId);
            if (! event) {
                return;
            }
            setFormForEdit(event);
        });

        $(document).on('click', '.js-calendar-event-delete', async function () {
            const eventId = $(this).data('event-id');
            const event = cachedEvents.find((item) => item.id === eventId);
            if (! event) {
                return;
            }

            const result = await confirmDialog({
                title: 'Delete calendar event?',
                text: 'This event will be removed from the school calendar.',
                confirmButtonText: 'Delete',
            });

            if (! result.isConfirmed) {
                return;
            }

            $.ajax({
                url: deleteUrlTemplate.replace('EVENT_ID', eventId),
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                success() {
                    cachedEvents = cachedEvents.filter((item) => item.id !== eventId);
                    renderCalendar(currentYear, currentMonth, selectedDate);
                    renderEventsForDate(selectedDate);
                    successToast('Calendar event deleted.');
                },
                error() {
                    errorAlert('Unable to delete the calendar event.');
                },
            });
        });

        function loadEventsForMonth(year, month, dateToRender) {
            const range = getMonthRange(year, month);
            $.ajax({
                url: listUrl,
                method: 'GET',
                data: {
                    start: formatDate(range.start),
                    end: formatDate(range.end),
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                success(response) {
                    cachedEvents = response.events || [];
                    renderCalendar(year, month, dateToRender);
                    renderEventsForDate(dateToRender);
                },
                error() {
                    cachedEvents = [];
                    renderCalendar(year, month, dateToRender);
                    renderEventsForDate(dateToRender);
                    errorAlert('Unable to load calendar events.');
                },
            });
        }

        function renderCalendar(year, month, selected) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();

            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];

            const dayNames = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

            const prevMonth = new Date(year, month, 0);
            const daysInPrevMonth = prevMonth.getDate();

            let html = `
                <div class="calendar-header mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <button type="button" class="calendar-nav-btn" data-direction="prev" aria-label="Previous month">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <h3 class="text-base font-semibold text-foreground">${monthNames[month]} ${year}</h3>
                        <button type="button" class="calendar-nav-btn" data-direction="next" aria-label="Next month">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 mb-2">
                        ${dayNames.map(day => `<div class="text-center text-xs font-medium text-foreground/70 py-1">${day}</div>`).join('')}
                    </div>
                </div>
                <div class="calendar-grid">
            `;

            for (let i = startingDayOfWeek - 1; i >= 0; i -= 1) {
                const date = daysInPrevMonth - i;
                const dateObj = new Date(year, month - 1, date);
                html += buildCalendarCell(dateObj, selected, true);
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const dateObj = new Date(year, month, day);
                html += buildCalendarCell(dateObj, selected, false);
            }

            const totalCells = 42;
            const cellsUsed = startingDayOfWeek + daysInMonth;
            const remainingCells = totalCells - cellsUsed;
            for (let day = 1; day <= remainingCells; day += 1) {
                const dateObj = new Date(year, month + 1, day);
                html += buildCalendarCell(dateObj, selected, true);
            }

            html += '</div>';
            $calendarEl.html(html);

            applyEventMarkers();

            $calendarEl.find('.calendar-day').on('click', function () {
                const dateStr = $(this).data('date');
                if (! dateStr) {
                    return;
                }
                const date = new Date(`${dateStr}T00:00:00`);
                selectedDate = date;
                currentMonth = date.getMonth();
                currentYear = date.getFullYear();
                renderCalendar(currentYear, currentMonth, selectedDate);
                renderEventsForDate(date);
                const url = new URL(window.location);
                url.searchParams.set('date', formatDate(date));
                window.history.pushState({}, '', url);
            });

            $calendarEl.find('.calendar-nav-btn').on('click', function () {
                const direction = $(this).data('direction');
                if (direction === 'prev') {
                    if (currentMonth === 0) {
                        currentMonth = 11;
                        currentYear -= 1;
                    } else {
                        currentMonth -= 1;
                    }
                } else {
                    if (currentMonth === 11) {
                        currentMonth = 0;
                        currentYear += 1;
                    } else {
                        currentMonth += 1;
                    }
                }
                loadEventsForMonth(currentYear, currentMonth, selectedDate);
            });
        }

        function buildCalendarCell(dateObj, selected, isOtherMonth) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const dateObjNormalized = new Date(dateObj);
            dateObjNormalized.setHours(0, 0, 0, 0);
            const selectedNormalized = selected ? new Date(selected) : null;
            if (selectedNormalized) {
                selectedNormalized.setHours(0, 0, 0, 0);
            }

            const isSelected = selectedNormalized
                && dateObjNormalized.getTime() === selectedNormalized.getTime();
            const isToday = dateObjNormalized.getTime() === today.getTime();

            let classes = 'calendar-day';
            if (isOtherMonth) classes += ' other-month';
            if (isSelected) classes += ' selected';
            else if (isToday) classes += ' today';

            return `<div class="${classes}" data-date="${formatDate(dateObj)}">
                ${dateObj.getDate()}
                <span class="calendar-dot" style="display: none;"></span>
            </div>`;
        }

        function applyEventMarkers() {
            $calendarEl.find('.calendar-day').each(function () {
                const dateStr = $(this).data('date');
                const dot = $(this).find('.calendar-dot');
                if (! dateStr || ! dot.length) {
                    return;
                }

                const events = getEventsForDate(dateStr);
                if (events.length === 0) {
                    dot.hide();
                    $(this).removeClass('calendar-day-holiday calendar-day-event');
                    return;
                }

                const hasHoliday = events.some((event) => event.is_holiday);
                $(this).toggleClass('calendar-day-holiday', hasHoliday);
                $(this).toggleClass('calendar-day-event', ! hasHoliday);
                dot
                    .show()
                    .toggleClass('calendar-dot-holiday', hasHoliday)
                    .toggleClass('calendar-dot-event', ! hasHoliday);
            });
        }

        function renderEventsForDate(date) {
            const dateStr = formatDate(date);
            const displayDate = date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            $dateLabel.text(`Events for ${displayDate}`);

            const events = getEventsForDate(dateStr);
            if (! events.length) {
                $eventList.html('<p class="text-sm text-foreground/60">No events scheduled for this date.</p>');
                return;
            }

            let html = '<div class="space-y-4">';
            events.forEach((event) => {
                const badgeClass = event.is_holiday
                    ? 'bg-danger/10 text-danger'
                    : 'bg-primary/10 text-primary';
                html += `
                    <div class="border border-border rounded-lg p-4 flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-semibold text-foreground">${event.title}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full ${badgeClass}">${event.event_type_label || ''}</span>
                            </div>
                            <div class="text-xs text-foreground/60">
                                ${event.start_date} - ${event.end_date}
                            </div>
                            ${event.notes ? `<p class="text-sm text-foreground/70 mt-2">${event.notes}</p>` : ''}
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                class="js-calendar-event-edit inline-flex items-center px-2 py-1 border border-border rounded-lg text-xs hover:bg-background/subtle"
                                data-event-id="${event.id}">
                                Edit
                            </button>
                            <button type="button"
                                class="js-calendar-event-delete inline-flex items-center px-2 py-1 border border-border rounded-lg text-xs text-danger hover:bg-danger/10"
                                data-event-id="${event.id}">
                                Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            $eventList.html(html);
        }

        function getEventsForDate(dateStr) {
            return cachedEvents.filter((event) => dateStr >= event.start_date && dateStr <= event.end_date);
        }

        function getMonthRange(year, month) {
            return {
                start: new Date(year, month, 1),
                end: new Date(year, month + 1, 0),
            };
        }

        function setFormForEdit(event) {
            $('#calendar_event_id').val(event.id);
            $('#event_title').val(event.title);
            $('#event_type').val(event.event_type);
            $('#event_start_date').val(event.start_date);
            $('#event_end_date').val(event.end_date);
            $('#event_notes').val(event.notes || '');
            $cancelBtn.removeClass('hidden');
            $submitBtn.text('Update Event');
        }

        function resetForm() {
            clearErrors();
            $('#calendar_event_id').val('');
            $('#event_title').val('');
            $('#event_type').val('');
            $('#event_start_date').val('');
            $('#event_end_date').val('');
            $('#event_notes').val('');
            $cancelBtn.addClass('hidden');
            $submitBtn.text('Save Event');
        }

        function clearErrors() {
            $form.find('[data-error-for]').addClass('hidden').text('');
        }

        function showErrors(errors) {
            Object.entries(errors).forEach(([field, messages]) => {
                const $error = $form.find(`[data-error-for="${field}"]`);
                if ($error.length) {
                    $error.removeClass('hidden').text(messages[0]);
                }
            });
        }

        function formatDate(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }
    });
})(window.jQuery);
