NOVA · Admin Schedule Calendar PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Admin Schedule Calendar provides a FullCalendar.js-powered visual calendar view of all schedules across all therapists and students. Admins can view schedule details and filter by therapist.

2. FUNCTIONAL SCOPE

   2.1 Calendar View
   Route: GET /admin/schedule-calendar
   - FullCalendar.js integration with month/week/day views
   - Color-coded events by therapist or status
   - Click on event to view schedule details

   2.2 Events Endpoint
   Route: GET /admin/schedule-calendar/events
   - Returns JSON calendar events for the visible date range
   - Filterable by therapist via `ScheduleCalendarFilterRequest`

   2.3 Schedule Detail
   Route: GET /admin/schedule-calendar/{id}
   - Returns JSON with complete schedule detail: student, therapist, service, SSA, school, parent info
   - Displayed in a popover or modal on the calendar

3. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\ScheduleCalendarController`
   Services: `ScheduleService`, `TherapistService`
   Policy: `SchedulePolicy`
   View: `admin/schedule-calendar/index.blade.php`

4. NAVIGATION
   Appears under "Session Logs" top-level admin menu as "Schedule Calendar" entry.
