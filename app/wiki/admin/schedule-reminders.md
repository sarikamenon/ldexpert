# Schedule Reminders

The system includes an automated email reminder feature for upcoming therapy sessions.

## Overview

-   **Frequency:** Checks every 30 minutes.
-   **Recipients:** Therapist, Student, and Guardian (Parent).
-   **Triggers:**
    -   **24 Hours Before:** Sends a reminder 24 hours before the session start time.
    -   **1 Hour Before:** Sends a reminder 1 hour before the session start time.

## Email Content

The email includes:

-   **Session Type:** The name of the service (e.g., Speech Therapy, Occupational Therapy).
-   **Participants:** Names of the Therapist and Student.
-   **Date & Time:** The scheduled date and time, **converted to the recipient's local timezone**.
-   **Location:** Specific location details if available.
-   **Notes:** Any notes attached to the schedule.
-   **Contact Info:** The therapist's email and phone number for rescheduling inquiries.

## Technical Implementation

### Console Command

`App\Console\Commands\SendScheduleReminders`

-   Runs `everyThirtyMinutes`.
-   Queries for schedules starting within a specific window (e.g., 24h to 24.5h from now) to avoid duplicate emails.
-   Uses `ScheduleRepositoryInterface` to fetch relevant schedules.

### Mailable

`App\Mail\ScheduleReminderMail`

-   Accepts the `Schedule`, `type` (24h/1h), `recipientName`, and `timezone`.
-   Uses `UserTimezoneService` to format dates and times according to the recipient's preference.

### Repository

`EloquentScheduleRepository::getSchedulesInWindow($start, $end)`

-   Fetches schedules where the combined `schedule_date` and `start_time` fall within the given UTC window.
