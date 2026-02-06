import { initSelectBoxes } from '../common/select-box';

(function ($) {
    'use strict';

    $(document).ready(function () {
        // initSelectBoxes is already called by select-box.js, but we can call it again
        // if needed for dynamically added elements
        initSelectBoxes();

        const $calendarEl = $('#calendar');
        const $selectedDateHeader = $('#selectedDateHeader');
        const $todayViewBtn = $('.calendar-today-btn');
        const $scheduleFiltersForm = $('#scheduleFiltersForm');
        const $scheduleList = $('#scheduleList');
        const $schoolEventList = $('#schoolEventList');
        const addScheduleButton = document.getElementById('addScheduleButton');
        const ssaSelectionModal = document.getElementById('ssaSelectionModal');
        const ssaSelectionForm = document.getElementById('ssaSelectionForm');
        const cancelSSASelection = document.getElementById('cancelSSASelection');

        if (! $calendarEl.length) {
            return;
        }

        const therapistTimezoneLabel = $calendarEl.data('therapist-timezone-label') || 'Central Time (CT)';
        const calendarEventsUrl = $calendarEl.data('calendar-events-url');
        let calendarEvents = parseCalendarEvents($calendarEl.data('calendar-events'));
        const selectedDateStr = $calendarEl.data('selected-date');
        let selectedDate = selectedDateStr
            ? new Date(`${selectedDateStr}T00:00:00`)
            : new Date();

        let currentMonth = selectedDate.getMonth();
        let currentYear = selectedDate.getFullYear();

        renderCalendar(currentYear, currentMonth, selectedDate);
        loadCalendarEventsForMonth(currentYear, currentMonth);

        // Load schedules for the initial selected date
        loadScheduleForDate(selectedDate);

        // Handle Add Schedule button click - show SSA selection modal
        if (addScheduleButton) {
            addScheduleButton.addEventListener('click', (e) => {
                e.preventDefault();
                if (addScheduleButton.disabled) {
                    return;
                }
                if (ssaSelectionModal) {
                    const date = getSelectedDate() || new Date();
                    // Store the date the user is creating the schedule for so we can use it
                    // after SSA selection (works for both top button and empty-state button)
                    ssaSelectionModal.dataset.scheduleDate = formatDate(date);
                    ssaSelectionModal.classList.remove('hidden');
                    // Initialize select box for SSA dropdown
                    initSelectBoxes();
                }
            });
        }

        // Handle cancel button
        if (cancelSSASelection) {
            cancelSSASelection.addEventListener('click', () => {
                if (ssaSelectionModal) {
                    ssaSelectionModal.classList.add('hidden');
                }
            });
        }

        // Handle SSA selection form submission
        if (ssaSelectionForm) {
            ssaSelectionForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const ssaId = document.getElementById('ssa_id').value;
                if (! ssaId) {
                    return;
                }

                // Prefer the date explicitly stored on the modal (set when opening it),
                // otherwise fall back to the currently selected calendar date or today.
                let date;
                const modalDateStr = ssaSelectionModal?.dataset.scheduleDate;
                if (modalDateStr) {
                    date = new Date(`${modalDateStr}T00:00:00`);
                } else {
                    date = getSelectedDate() || new Date();
                }

                const baseUrl = addScheduleButton?.dataset.createBase || '/therapist/schedule/create';
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('date', formatDate(date));
                url.searchParams.set('ssa_id', ssaId);

                window.location.href = url.toString();
            });
        }

        // Close modal when clicking outside
        if (ssaSelectionModal) {
            ssaSelectionModal.addEventListener('click', (e) => {
                if (e.target === ssaSelectionModal) {
                    ssaSelectionModal.classList.add('hidden');
                }
            });
        }

        $todayViewBtn.on('click', () => {
            const today = new Date();
            selectedDate = today; // Update the stored selected date
            currentMonth = today.getMonth();
            currentYear = today.getFullYear();
            renderCalendar(currentYear, currentMonth, selectedDate);
            updateSelectedDate(today);
            loadCalendarEventsForMonth(currentYear, currentMonth);
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

        // Handle edit button clicks (fallback for dynamically rendered schedules)
        $(document).on('click', '.schedule-edit-btn', function () {
            const scheduleId = $(this).data('schedule-id');
            if (scheduleId) {
                window.location.href = `/therapist/schedule/${scheduleId}/edit`;
            }
        });

        // Handle schedule details view button clicks (details + view session)
        $(document).on('click', '.schedule-details-view-btn, .schedule-view-session-btn', function () {
            const scheduleId = $(this).data('schedule-id');
            if (! scheduleId) {
                return;
            }

            const $content = $('#scheduleDetailsContent');
            $content.html('<div class="text-center py-12"><p class="text-foreground/70">Loading schedule details...</p></div>');

            // Open modal
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'scheduleDetailsModal' }));

            // Fetch schedule details
            $.ajax({
                url: `/therapist/schedule/${scheduleId}`,
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                success(response) {
                    renderScheduleDetails(response.schedule);
                },
                error(xhr) {
                    let errorMessage = 'Failed to load schedule details.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    $content.html(`<div class="text-center py-12"><p class="text-danger">${errorMessage}</p></div>`);
                },
            });
        });

        function renderScheduleDetails(schedule) {
            const $content = $('#scheduleDetailsContent');
            let html = '';

            // Top row: SSA Basic Details and Schedule Details side by side
            html += '<div class="grid grid-cols-2 gap-4 mb-4">';

            // SSA Basic Details (Left Column)
            if (schedule.ssa) {
                html += `
                    <div class="bg-background/subtle rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-foreground mb-3">SSA Basic Details</h4>
                        <div class="space-y-2.5 text-sm">
                            <div>
                                <div class="text-foreground/70 mb-1">Service</div>
                                <div class="text-foreground font-medium" title="${schedule.ssa.service?.name || schedule.service?.name || '-'}">${schedule.ssa.service?.name || schedule.service?.name || '-'}</div>
                            </div>
                            <div>
                                <div class="text-foreground/70 mb-1">Date Range</div>
                                <div class="text-foreground font-medium">${schedule.ssa.start_date_formatted || schedule.ssa.start_date || '-'}</div>
                                <div class="text-foreground font-medium">${schedule.ssa.end_date_formatted || schedule.ssa.end_date || '-'}</div>
                            </div>
                            <div>
                                <div class="text-foreground/70 mb-1">Session Details</div>
                                <div class="text-foreground font-medium">Minutes: ${schedule.ssa.minutes_per_session || '-'} x ${schedule.ssa.sessions_per_frequency || '-'}</div>
                                <div class="text-foreground font-medium">Frequency: ${schedule.ssa.frequency ? schedule.ssa.frequency.replace('_', '-').replace(/\b\w/g, l => l.toUpperCase()) : '-'}</div>
                            </div>
                            <div>
                                <div class="text-foreground/70 mb-1">Minutes & Status</div>
                                <div class="text-foreground font-medium">THO: ${schedule.ssa.tho_minutes ? schedule.ssa.tho_minutes.toLocaleString() : '0'}</div>
                                <div class="text-foreground font-medium">Served: ${schedule.ssa.served_minutes ? schedule.ssa.served_minutes.toLocaleString() : '0'}</div>
                                <div class="text-foreground font-medium">Status: ${schedule.ssa.status ? schedule.ssa.status.toUpperCase() : '-'}</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Schedule Details (Right Column)
            html += `
                <div class="bg-background/subtle rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-foreground mb-3">Schedule Details</h4>
                    <div class="space-y-2.5 text-sm">
                        <div>
                            <div class="text-foreground/70 mb-1">Date</div>
                            <div class="text-foreground font-medium">${schedule.schedule_date_formatted || schedule.schedule_date || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Time</div>
                            <div class="text-foreground font-medium">${schedule.start_time_formatted || schedule.start_time || '-'} - ${schedule.end_time_formatted || schedule.end_time || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Duration</div>
                            <div class="text-foreground font-medium">${schedule.duration_formatted || schedule.duration_minutes + 'm' || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Service</div>
                            <div class="text-foreground font-medium" title="${schedule.service?.name || '-'}">${schedule.service?.name || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Billing Status</div>
                            <div class="text-foreground font-medium">${schedule.billing_status ? schedule.billing_status.replace('_', ' ').toUpperCase() : '-'}</div>
                        </div>
                        ${schedule.location_details ? `
                            <div class="pt-2.5">
                                <div class="text-foreground/70 mb-1">Meeting Details</div>
                                <div class="text-foreground leading-relaxed whitespace-pre-wrap">${schedule.location_details}</div>
                            </div>
                        ` : ''}
                        ${schedule.notes ? `
                            <div class="pt-2.5">
                                <div class="text-foreground/70 mb-1">Notes</div>
                                <div class="text-foreground leading-relaxed whitespace-pre-wrap">${schedule.notes}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            html += '</div>'; // Close top row grid

            // Bottom row: Student Details and Parent Information side by side
            html += '<div class="grid grid-cols-2 gap-4">';

            // Student Details (Left Column)
            html += `
                <div class="bg-background/subtle rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-foreground mb-3">Student Details</h4>
                    <div class="space-y-2.5 text-sm">
                        <div>
                            <div class="text-foreground/70 mb-1">Name</div>
                            <div class="text-foreground font-medium" title="${schedule.student?.name || '-'}">${schedule.student?.name || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">ID Number</div>
                            <div class="text-foreground font-medium">${schedule.student?.id_number || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Email</div>
                            <div class="text-foreground font-medium" title="${schedule.student?.email || '-'}">${schedule.student?.email || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">School</div>
                            <div class="text-foreground font-medium" title="${schedule.school?.name || '-'}">${schedule.school?.name || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Timezone</div>
                            <div class="text-foreground font-medium">${schedule.student?.timezone || '-'}</div>
                        </div>
                    </div>
                </div>
            `;

            // Parent Details (Right Column)
            html += `
                <div class="bg-background/subtle rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-foreground mb-3">Parent Information</h4>
                    <div class="space-y-2.5 text-sm">
                        <div>
                            <div class="text-foreground/70 mb-1">Name</div>
                            <div class="text-foreground font-medium" title="${schedule.parent?.name || '-'}">${schedule.parent?.name || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Email</div>
                            <div class="text-foreground font-medium" title="${schedule.parent?.email || '-'}">${schedule.parent?.email || '-'}</div>
                        </div>
                        <div>
                            <div class="text-foreground/70 mb-1">Phone</div>
                            <div class="text-foreground font-medium">${schedule.parent?.phone || '-'}</div>
                        </div>
                    </div>
                </div>
            `;

            html += '</div>'; // Close bottom row grid

            $content.html(html);
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
                
                let classes = 'calendar-day other-month';
                if (isSelected) classes += ' selected';
                else if (isToday) classes += ' today';
                
                html += `<div class="${classes}" data-date="${formatDate(dateObj)}">${date}</div>`;
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const dateObj = new Date(year, month, day);
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
                // Selected takes priority - show in blue
                if (isSelected) {
                    classes += ' selected';
                } else if (isToday) {
                    // Today shows in gray only if not selected
                    classes += ' today';
                }

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
                
                let classes = 'calendar-day other-month';
                if (isSelected) classes += ' selected';
                else if (isToday) classes += ' today';
                
                html += `<div class="${classes}" data-date="${formatDate(dateObj)}">${day}</div>`;
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
                selectedDate = date; // Update the stored selected date
                currentMonth = date.getMonth();
                currentYear = date.getFullYear();
                renderCalendar(currentYear, currentMonth, selectedDate);
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
                renderCalendar(currentYear, currentMonth, selectedDate);
                loadCalendarEventsForMonth(currentYear, currentMonth);
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
                $selectedDateHeader.text(`${date.getDate()} ${monthNames[date.getMonth()]}, ${date.getFullYear()} - ${timeStr} (${therapistTimezoneLabel})`);
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
                    renderSchoolEvents(response.events || [], date);
                },
                error(xhr) {
                    let errorMessage = 'Failed to load schedules.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    $scheduleList.html(`<div class="text-center py-12"><p class="text-danger">${errorMessage}</p></div>`);
                    renderSchoolEvents([], date);
                },
            });
        }

        function loadCalendarEventsForMonth(year, month) {
            if (! calendarEventsUrl) {
                return;
            }
            const range = getMonthRange(year, month);
            $.ajax({
                url: calendarEventsUrl,
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
                    calendarEvents = response.events || [];
                    applyEventMarkers();
                },
            });
        }

        function renderSchoolEvents(events, selected) {
            if (! $schoolEventList.length) {
                return;
            }
            const dateLabel = selected
                ? selected.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
                : 'Selected date';

            const dayEvents = (events && events.length)
                ? events
                : getEventsForDate(formatDate(selected));

            if (! dayEvents.length) {
                $schoolEventList.html(`
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold text-foreground">School Events</h3>
                        <p class="text-xs text-foreground/60">For ${dateLabel}</p>
                    </div>
                    <p class="text-sm text-foreground/60">No school events on this date.</p>
                `);
                return;
            }

            let html = `
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-foreground">School Events</h3>
                    <p class="text-xs text-foreground/60">For ${dateLabel}</p>
                </div>
                <div class="space-y-3">
            `;

            dayEvents.forEach((event) => {
                const badgeClass = event.is_holiday
                    ? 'bg-danger/10 text-danger'
                    : 'bg-primary/10 text-primary';
                html += `
                    <div class="border border-border rounded-lg p-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-foreground">${event.title}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full ${badgeClass}">
                                ${event.event_type_label || ''}
                            </span>
                        </div>
                        <div class="text-xs text-foreground/60 mt-1">${event.start_date} - ${event.end_date}</div>
                        ${event.notes ? `<p class="text-xs text-foreground/70 mt-2">${event.notes}</p>` : ''}
                    </div>
                `;
            });
            html += '</div>';
            $schoolEventList.html(html);
        }

        function renderScheduleList(schedules, selectedDate) {
            if (! schedules || schedules.length === 0) {
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];
                const dateStr = `${monthNames[selectedDate.getMonth()]} ${selectedDate.getDate()}${getOrdinalSuffix(selectedDate.getDate())}`;
                $scheduleList.html(`
                    <div class="text-center py-12">
                        <p class="text-foreground/70 mb-4">You don't have any schedule for ${dateStr}.</p>
                        <button type="button"
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium js-add-schedule-inline"
                            data-date="${formatDate(selectedDate)}">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            ADD NEW SCHEDULE
                        </button>
                    </div>
                `);

                // Wire up the inline "Add New Schedule" button to use the same SSA selection flow
                // as the top-right "Add New Schedule" button.
                const inlineButton = document.querySelector('.js-add-schedule-inline');
                if (inlineButton && ssaSelectionModal) {
                    inlineButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        const dateStrAttr = inlineButton.getAttribute('data-date');
                        let dateForSchedule;
                        if (dateStrAttr) {
                            dateForSchedule = new Date(`${dateStrAttr}T00:00:00`);
                        } else {
                            dateForSchedule = getSelectedDate() || selectedDate || new Date();
                        }

                        ssaSelectionModal.dataset.scheduleDate = formatDate(dateForSchedule);
                        ssaSelectionModal.classList.remove('hidden');
                        initSelectBoxes();
                    });
                }

                return;
            }

            let html = '';
            schedules.forEach((schedule) => {
                const isPast = Boolean(schedule.is_past);
                const isBilled = Boolean(schedule.is_billed);
                const isPendingBilling = schedule.billing_status === 'pending';

                html += `
                    <div class="border border-border rounded-lg p-4 mb-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-4 mb-2">
                                    <span class="text-sm font-medium text-foreground">${schedule.start_time || ''}</span>
                                    ${schedule.end_time ? `<span class="text-sm text-foreground/70">-</span><span class="text-sm font-medium text-foreground">${schedule.end_time}</span>` : ''}
                                </div>
                                ${schedule.student ? `
                                    <div class="mb-1">
                                        ${schedule.student_url
                                            ? `<a href="${schedule.student_url}" class="font-semibold text-accent text-sm hover:underline">${schedule.student}</a>`
                                            : `<span class="font-semibold text-accent text-sm">${schedule.student}</span>`
                                        }
                                    </div>
                                ` : ''}
                                ${schedule.service ? `<div class="text-sm text-foreground/70 mb-2">${schedule.service}</div>` : ''}
                            </div>
                            <div class="flex items-center gap-2 ml-4">
                                <button type="button" class="schedule-details-view-btn p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors"
                                    data-schedule-id="${schedule.id}"
                                    title="View Details">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-foreground" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                ${!isBilled ? (
                                    schedule.edit_url
                                        ? `
                                            <a href="${schedule.edit_url}" class="p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors" title="Edit Schedule">
                                                <svg class="w-5 h-5 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        `
                                        : `
                                            <button type="button" class="schedule-edit-btn p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors" data-schedule-id="${schedule.id}" title="Edit Schedule">
                                                <svg class="w-5 h-5 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        `
                                ) : ''}

                                ${isPast && isPendingBilling ? (
                                    schedule.bill_url
                                        ? `
                                            <a href="${schedule.bill_url}" class="p-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors" title="Bill Your Session">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </a>
                                        `
                                        : `
                                            <button type="button" class="schedule-bill-btn p-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors" data-schedule-id="${schedule.id}" title="Bill Your Session">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        `
                                ) : ''}

                                ${isPast && isBilled ? (
                                    schedule.session_log_url
                                        ? `
                                            <a href="${schedule.session_log_url}" class="schedule-view-session-link p-2 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors" title="View Session Log">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </a>
                                        `
                                        : `
                                            <button type="button" class="schedule-view-session-btn p-2 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors" data-schedule-id="${schedule.id}" title="View Session">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        `
                                ) : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            $scheduleList.html(html);
        }

        function applyEventMarkers() {
            $calendarEl.find('.calendar-day').each(function () {
                const dateStr = $(this).data('date');
                const dot = $(this).find('.calendar-dot');
                if (! dateStr || ! dot.length) {
                    return;
                }

                if (! calendarEvents || calendarEvents.length === 0) {
                    dot.hide();
                    $(this).removeClass('calendar-day-holiday calendar-day-event');
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

        function getEventsForDate(dateStr) {
            if (! calendarEvents || calendarEvents.length === 0) {
                return [];
            }
            return calendarEvents.filter((event) => dateStr >= event.start_date && dateStr <= event.end_date);
        }

        function getMonthRange(year, month) {
            return {
                start: new Date(year, month, 1),
                end: new Date(year, month + 1, 0),
            };
        }

        function parseCalendarEvents(data) {
            if (! data) {
                return [];
            }
            if (Array.isArray(data)) {
                return data;
            }
            try {
                return JSON.parse(data);
            } catch (e) {
                return [];
            }
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

