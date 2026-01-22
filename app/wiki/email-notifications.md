# Email Notifications PRD

## Purpose

The Email Notifications module provides automated and on-demand email communications throughout the NOVA platform. It ensures stakeholders (therapists, students, guardians, admins) receive timely notifications about schedules, sessions, billing, invoices, and system events. All emails respect user timezone preferences and provide clear, actionable information.

## Personas

- **Therapist** — receives schedule notifications, reminders, billing notifications.
- **Student** — receives schedule notifications and reminders.
- **Guardian/Parent** — receives schedule reminders for their student.
- **Administrator** — receives system notifications and can trigger emails for invoices/bills.

## Current Implementation

### Schedule Notifications ✅

**When Created or Updated:**
- **Recipients:** Therapist and Student
- **Trigger:** Event-based via `ScheduleCreated` and `ScheduleUpdated` events
- **Implementation:** `App\Listeners\SendScheduleNotification` listener
- **Mailable:** `App\Mail\ScheduleNotificationMail`
- **Queue:** Sent immediately via queue (database queue by default)
- **Content:** 
  - Subject: "NOVA - New Schedule: [Date]" or "NOVA - Schedule Update: [Date]"
  - Session details (therapist, student, service, date, time, location, notes)
  - Therapist contact information for rescheduling
- **View:** `resources/views/emails/schedule-notification.blade.php`

### Schedule Reminders ✅

**Automated Reminders:**
- **Frequency:** Runs every 30 minutes via scheduled command
- **Recipients:** Therapist, Student, and Guardian (Parent)
- **Triggers:**
  - **48 Hours Before:** Sends reminder 48 hours before session start time
  - **2 Hours Before:** Sends reminder 2 hours before session start time
- **Implementation:** 
  - Console Command: `App\Console\Commands\SendScheduleReminders`
  - Mailable: `App\Mail\ScheduleReminderMail`
  - Runs: Every 30 minutes (configured in `app/Console/Kernel.php`)
- **Features:**
  - 30-minute windows to avoid duplicates in overlapping runs
  - Recipients deduplicated by email before queueing
  - Timezone-aware: Dates/times converted to recipient's local timezone via `UserTimezoneService`
  - Fallback order: profile timezone → user timezone → UTC
- **Content:**
  - Subject: "Reminder: Upcoming Session with [Therapist] in [48 hours/2 hours]"
  - Session type, participants, date & time (timezone-converted), location, notes
  - Therapist contact information
- **View:** `resources/views/emails/schedule-reminder.blade.php`
- **Repository:** `EloquentScheduleRepository::getSchedulesInWindow($start, $end)`

**See [Schedule Reminders PRD](./admin/schedule-reminders.md) for detailed documentation.**

### Invoice Notifications ✅

**When Invoice Sent:**
- **Recipients:** School contacts (invoice email addresses)
- **Trigger:** Admin action via `/admin/invoices/{invoice}/send` route
- **Implementation:** `App\Domain\Invoice\Services\InvoiceService::sendInvoice`
- **Mailable:** `App\Mail\InvoiceMail`
- **Queue:** Sent via queue
- **Content:**
  - Subject: "Invoice [Invoice Number] from [Company Name]"
  - Invoice PDF attachment
  - Invoice summary (total, due date, billing period)
  - Payment instructions
- **View:** `resources/views/emails/invoice.blade.php`

### Therapist Bill Notifications ✅

**When Bill Sent:**
- **Recipients:** Therapist (therapist's email address)
- **Trigger:** Admin action via `/admin/billing/therapist-bills/{bill}/send` route
- **Implementation:** `App\Domain\Billing\Services\TherapistBillService::sendBill`
- **Mailable:** `App\Mail\TherapistBillMail`
- **Queue:** Sent via queue
- **Content:**
  - Subject: "Bill [Bill Number] from [Company Name]"
  - Bill PDF attachment
  - Bill summary (total, billing period, session count)
  - Payment information
- **View:** `resources/views/emails/therapist-bill.blade.php`

### Welcome Emails ✅

**User Onboarding:**
- **Recipients:** New users (students, therapists, admins)
- **Trigger:** User creation via `user:create-welcome` command or user service
- **Implementation:** `App\Mail\WelcomeUserMail`
- **Queue:** Sent via queue
- **Content:** Role-specific welcome messages with login instructions and platform overview
- **View:** `resources/views/emails/welcome-user.blade.php`

### School Notifications ✅

**School Status Changes:**
- **Recipients:** School contacts
- **Trigger:** Event-based when school status changes
- **Implementation:** `App\Notifications\SchoolStatusChangedNotification`
- **Events:** School activation/deactivation, contract status changes

**School Creation:**
- **Recipients:** School contacts
- **Trigger:** Event-based when school is created
- **Implementation:** `App\Notifications\SchoolCreatedNotification`

## Technical Implementation

### Email Configuration

- **Default Mailer:** `log` (for development)
- **Production Options:** SMTP, Postmark, SES, Resend available via `config/mail.php` and `config/services.php`
- **Queue:** Database queue by default (configurable to Redis, SQS, etc.)
- **Queue Workers:** Must be running for queued emails to be sent

### Timezone Handling

All time-sensitive emails use `App\Domain\Time\UserTimezoneService` to convert dates/times to recipient's local timezone:

1. Checks user profile timezone (`user_profiles.timezone`)
2. Falls back to user timezone (`users.timezone`)
3. Defaults to UTC if no timezone set

### Queue Processing

- **Connection:** Database queue by default
- **Tables:** `jobs` and `failed_jobs`
- **Worker Command:** `php artisan queue:work` or `php artisan queue:listen`
- **Scheduler:** Runs every 30 minutes for schedule reminders via `php artisan schedule:run`
- **Failure Handling:** Failed jobs logged to `failed_jobs` table with full error context

### Event-Driven Notifications

- `ScheduleCreated` event → `SendScheduleNotification` listener
- `ScheduleUpdated` event → `SendScheduleNotification` listener
- Events dispatched automatically when schedules are created/updated
- Listeners implement `ShouldQueue` for asynchronous processing

## Routes & Controllers

### Admin Routes (Email Sending)

- `POST /admin/invoices/{invoice}/send` - Send invoice via email
- `POST /admin/billing/therapist-bills/{bill}/send` - Send therapist bill via email

### Console Commands

- `schedule:send-reminders` - Automated schedule reminder emails (runs every 30 minutes)
- `user:create-welcome {name} {email} [--password=] [--role=]` - Creates user and sends welcome email

## Workflows

### 1. Schedule Creation/Update Notification

1. Therapist or admin creates/updates a schedule
2. System dispatches `ScheduleCreated` or `ScheduleUpdated` event
3. `SendScheduleNotification` listener queues email jobs
4. System sends email to therapist and student with schedule details
5. Emails include timezone-converted dates/times

### 2. Automated Schedule Reminders

1. `SendScheduleReminders` command runs every 30 minutes
2. System queries schedules in 48h and 2h windows
3. For each schedule, system identifies recipients (therapist, student, guardian)
4. System resolves timezone for each recipient
5. System deduplicates recipients by email
6. System queues reminder emails with timezone-converted content
7. Queue worker sends emails asynchronously

### 3. Invoice/Bill Sending

1. Admin creates invoice/bill in draft status
2. Admin clicks "Send" button
3. System generates PDF document
4. System queues email with PDF attachment
5. System updates invoice/bill status to "sent" and records `sent_at` timestamp
6. Queue worker sends email to recipient

## Email Templates

All email templates use Laravel's Mail components (`x-mail::message`, `x-mail::panel`, etc.) and are located in `resources/views/emails/`:

- `schedule-notification.blade.php` - Schedule creation/update notifications
- `schedule-reminder.blade.php` - Automated schedule reminders (48h and 2h)
- `invoice.blade.php` - School invoice notifications
- `therapist-bill.blade.php` - Therapist bill notifications
- `welcome-user.blade.php` - User welcome emails

Templates use Tailwind CSS compatible styling and follow consistent branding.

## Security & Privacy

- **Recipient Validation:** Emails only sent to verified email addresses
- **Authorization:** Only authorized users can trigger manual emails (admins for invoices/bills)
- **PII Handling:** Sensitive information handled per privacy requirements
- **Email Delivery:** Uses secure SMTP/TLS connections in production
- **Queue Security:** Queue jobs include user context for audit trails

## Dependencies

- **Schedule Module:** Provides schedule data for notifications and reminders
- **Invoice Module:** Provides invoice data for notifications
- **Billing Module:** Provides therapist bill data for notifications
- **User Module:** Provides user profiles and timezone data
- **Queue System:** Database queue (or configured alternative) for asynchronous processing
- **Mail Configuration:** SMTP/Postmark/SES/Resend for production email delivery

## Metrics & Monitoring

- **Delivery Rate:** Track email delivery success/failure rates
- **Open Rate:** Monitor email open rates (requires tracking pixels, future enhancement)
- **Click Rate:** Monitor link clicks (requires tracking, future enhancement)
- **Queue Performance:** Monitor queue processing times and backlog
- **Failed Jobs:** Track and alert on failed email jobs
- **Reminder Effectiveness:** Track reminder-to-attendance correlation

## Planned Scope (Future Enhancements)

### Notification Preferences

- **User Preferences:** Allow users to opt-in/opt-out of specific notification types
- **Frequency Controls:** Allow users to control reminder frequency (48h only, 2h only, both, none)
- **Channel Preferences:** Support email, SMS, push notifications (future)

### Additional Notification Types

- **SSA Expiration Warnings:** Notify admins/therapists before SSA expiration
- **Billing Cycle Notifications:** Notify admins when billing cycles close
- **Document Upload Notifications:** Notify when documents are uploaded to student/session records
- **Comment Notifications:** Notify when comments are added to student records
- **Session Submission Alerts:** Notify admins when sessions are submitted for approval

### Enhanced Email Features

- **Rich Templates:** Enhanced HTML templates with better branding
- **Unsubscribe Links:** Add unsubscribe functionality to all emails
- **Email Tracking:** Track opens and clicks for analytics
- **Bulk Email:** Support bulk email sending for announcements
- **Email Scheduling:** Schedule emails for future delivery

## Risks & Open Questions

- **Email Deliverability:** Need monitoring for spam folder delivery
- **Rate Limiting:** Consider rate limiting for high-volume email sends
- **Internationalization:** Future support for multi-language emails
- **Compliance:** Ensure compliance with CAN-SPAM, GDPR, and other email regulations
- **Bounce Handling:** Implement bounce handling and email validation
- **Unsubscribe Management:** Need centralized unsubscribe management system

## Version 2 Backlog

- **Notification Center:** In-app notification center with email digest options
- **SMS Integration:** Add SMS notifications for critical reminders
- **Push Notifications:** Mobile app push notifications
- **Email Templates Editor:** Admin UI for editing email templates
- **A/B Testing:** Test different email subject lines and content for effectiveness
- **Analytics Dashboard:** Dashboard showing email metrics and trends
