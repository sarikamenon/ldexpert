# Schedule Reminders

The system includes an automated email reminder feature for upcoming therapy sessions.

## Overview

-   **Frequency:** Checks every 30 minutes.
-   **Recipients:** Therapist, Student, and Guardian (Parent).
-   **Triggers:**
    -   **48 Hours Before:** Sends a reminder 48 hours before the session start time.
    -   **2 Hours Before:** Sends a reminder 2 hours before the session start time.
    -   Windows are 30 minutes wide to avoid duplicates in overlapping runs.
    -   Recipients are deduplicated by email before queueing messages.

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
-   Queries schedules in 30-minute windows (48h and 2h) using `ScheduleRepositoryInterface::getSchedulesInWindow`.
-   Resolves timezone per recipient via `UserTimezoneService` with fallback order: profile timezone → user timezone → UTC.
-   Deduplicates recipients by email, then queues mail.

### Mailable

`App\Mail\ScheduleReminderMail`

-   Accepts the `Schedule`, `type` (48h/2h), `recipientName`, and `timezone`.
-   Uses `UserTimezoneService` to format dates/times in the recipient's timezone.
-   Sent via the queue (database connection by default); ensure a worker is running.

### Repository

`EloquentScheduleRepository::getSchedulesInWindow($start, $end)`

-   Fetches schedules where the combined `schedule_date` and `start_time` fall within the given UTC window.
