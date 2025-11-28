import { initSelectBoxes } from '../common/select-box';

(function ($) {
    'use strict';

    $(document).ready(function () {
        initSelectBoxes();

        const $calendarEl = $('#calendar');
        const $selectedDateHeader = $('#selectedDateHeader');
        const $todayViewBtn = $('.calendar-today-btn');
        const $scheduleFiltersForm = $('#scheduleFiltersForm');
        const $scheduleList = $('#scheduleList');
        const addScheduleButton = document.getElementById('addScheduleButton');

        if (! $calendarEl.length) {
            return;
        }

        const selectedDateStr = $calendarEl.data('selected-date');
        const selectedDate = selectedDateStr
            ? new Date(`${selectedDateStr}T00:00:00`)
            : new Date();

        let currentMonth = selectedDate.getMonth();
        let currentYear = selectedDate.getFullYear();

        renderCalendar(currentYear, currentMonth, selectedDate);

        if (addScheduleButton) {
            addScheduleButton.addEventListener('click', () => {
                const date = getSelectedDate() || new Date();
                const baseUrl = addScheduleButton.dataset.createBase || addScheduleButton.getAttribute('href');
                if (! baseUrl) {
                    return;
                }
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('date', formatDate(date));
                addScheduleButton.href = url.toString();
            });
        }

        $todayViewBtn.on('click', () => {
            const today = new Date();
            currentMonth = today.getMonth();
            currentYear = today.getFullYear();
            renderCalendar(currentYear, currentMonth, today);
            updateSelectedDate(today);
            loadScheduleForDate(today);
        });

        if ($scheduleFiltersForm.length) {
            $scheduleFiltersForm.find('[data-select-box]').on('change', function () {
                const date = getSelectedDate();
                if (date) {
                    const url = new URL(window.location);
                    url.searchParams.set('date', formatDate(date));
                    url.searchParams.set('school_id', $(this).closest('form').find('[name="school_id"]').val() || '');
                    url.searchParams.set('student_id', $(this).closest('form').find('[name="student_id"]').val() || '');
                    window.location.href = url.toString();
                } else {
                    $scheduleFiltersForm.submit();
                }
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
                html += `<div class="calendar-day other-month" data-date="${formatDate(dateObj)}">${date}</div>`;
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const dateObj = new Date(year, month, day);
                const isSelected = selected
                    && dateObj.getDate() === selected.getDate()
                    && dateObj.getMonth() === selected.getMonth()
                    && dateObj.getFullYear() === selected.getFullYear();
                const isToday = dateObj.toDateString() === new Date().toDateString();

                let classes = 'calendar-day';
                if (isSelected) classes += ' selected';
                if (isToday) classes += ' today';

                html += `<div class="${classes}" data-date="${formatDate(dateObj)}">
                    ${day}
                    <span class="calendar-dot" style="display: none;"></span>
                </div>`;
            }

            const totalCells = 42;
            const cellsUsed = startingDayOfWeek + daysInMonth;
            const remainingCells = totalCells - cellsUsed;
            for (let day = 1; day <= remainingCells; day += 1) {
                const dateObj = new Date(year, month + 1, day);
                html += `<div class="calendar-day other-month" data-date="${formatDate(dateObj)}">${day}</div>`;
            }

            html += '</div>';
            $calendarEl.html(html);

            $calendarEl.find('.calendar-day').on('click', function () {
                const dateStr = $(this).data('date');
                if (! dateStr) {
                    return;
                }
                const date = new Date(`${dateStr}T00:00:00`);
                updateSelectedDate(date);
                loadScheduleForDate(date);
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
                renderCalendar(currentYear, currentMonth, selected);
            });
        }

        function formatDate(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }

        function getSelectedDate() {
            const selectedDay = $calendarEl.find('.calendar-day.selected');
            if (! selectedDay.length) {
                return null;
            }
            const dateStr = selectedDay.data('date');
            return dateStr ? new Date(`${dateStr}T00:00:00`) : null;
        }

        function updateSelectedDate(date) {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });

            if ($selectedDateHeader.length) {
                $selectedDateHeader.text(`${date.getDate()} ${monthNames[date.getMonth()]}, ${date.getFullYear()} - ${timeStr} (US/Central)`);
            }

            const url = new URL(window.location);
            url.searchParams.set('date', formatDate(date));
            window.history.pushState({}, '', url);
        }

        function loadScheduleForDate(date) {
            if (! $scheduleList.length) {
                return;
            }

            $scheduleList.html('<div class="text-center py-12"><p class="text-foreground/70">Loading schedules...</p></div>');

            const schoolId = $scheduleFiltersForm.find('[name="school_id"]').val() || '';
            const studentId = $scheduleFiltersForm.find('[name="student_id"]').val() || '';
            const scheduleUrl = $scheduleList.data('schedule-url') || '/therapist/schedule/schedules';

            $.ajax({
                url: scheduleUrl,
                method: 'GET',
                data: {
                    date: formatDate(date),
                    school_id: schoolId,
                    student_id: studentId,
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success(response) {
                    renderScheduleList(response.schedules, date);
                },
                error(xhr) {
                    let errorMessage = 'Failed to load schedules.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    $scheduleList.html(`<div class="text-center py-12"><p class="text-danger">${errorMessage}</p></div>`);
                },
            });
        }

        function renderScheduleList(schedules, selectedDate) {
            if (! schedules || schedules.length === 0) {
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];
                const dateStr = `${monthNames[selectedDate.getMonth()]} ${selectedDate.getDate()}${getOrdinalSuffix(selectedDate.getDate())}`;
                const createUrl = $scheduleList.data('create-url') || '/therapist/schedule/create';

                $scheduleList.html(`
                    <div class="text-center py-12">
                        <p class="text-foreground/70 mb-4">You don't have any schedule for ${dateStr}.</p>
                        <a href="${createUrl}?date=${formatDate(selectedDate)}"
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            + ADD NEW SCHEDULE
                        </a>
                    </div>
                `);
                return;
            }

            let html = '';
            schedules.forEach((schedule) => {
                html += `
                    <div class="border border-border rounded-lg p-4 mb-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-4 mb-2">
                                    <span class="text-sm font-medium text-foreground">${schedule.start_time || ''}</span>
                                    ${schedule.end_time ? `<span class="text-sm text-foreground/70">-</span><span class="text-sm font-medium text-foreground">${schedule.end_time}</span>` : ''}
                                </div>
                                ${schedule.school ? `<div class="mb-2"><span class="text-primary">${schedule.school}</span></div>` : ''}
                                ${schedule.student ? `<div class="mb-2"><span class="font-semibold text-foreground">${schedule.student}</span></div>` : ''}
                                ${schedule.service ? `<div class="text-sm text-foreground/70">${schedule.service}</div>` : ''}
                                ${schedule.notes ? `
                                    <div class="mt-3">
                                        <label class="block text-sm font-medium text-foreground/70 mb-1">Notes:</label>
                                        <textarea class="w-full border border-border rounded-lg px-3 py-2 text-sm" rows="3" readonly>${schedule.notes}</textarea>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="flex flex-col gap-2 ml-4">
                                <button type="button" class="schedule-edit-btn px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                                    EDIT SCHEDULE
                                </button>
                                <button type="button" class="schedule-bill-btn px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium flex items-center justify-center gap-2">
                                    BILL YOUR SESSION
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            $scheduleList.html(html);
        }

        function getOrdinalSuffix(day) {
            if (day > 3 && day < 21) return 'th';
            switch (day % 10) {
                case 1: return 'st';
                case 2: return 'nd';
                case 3: return 'rd';
                default: return 'th';
            }
        }
    });
})(window.jQuery);

