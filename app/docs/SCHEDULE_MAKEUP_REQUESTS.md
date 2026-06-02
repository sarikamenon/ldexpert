# Schedule Make-Up Requests

Structured workflow for offering parents a make-up session when a school is closed on a date
where their child had a scheduled session. It replaces the old fully-manual process (therapist
writes the email, tracks responses, books the make-up) with a single-table lifecycle that runs
from reminder → parent response → make-up booked, plus a global therapist-availability layer that
lets the parent self-reschedule the missed session in place.

> **Companion doc:** the availability + self-reschedule design rationale lives in
> [`SCHEDULE_MAKEUP_AVAILABILITY.md`](SCHEDULE_MAKEUP_AVAILABILITY.md). This document is the
> implementation reference for what shipped; that one is the design narrative for the booking layer.
>
> **Test status:** the feature is built and manually confirmed. The Pest suite now covers the
> unit, model, service, repository, command, and controller layers (see § Tests). Dusk browser
> tests are the remaining gap.

## Concepts

- **Make-up request** — one row in `schedule_makeup_requests` per (closure event × missed scheduled
  session). A 3-day closure where a student attends on each day produces 3 rows.
- **Batch** — every row that belongs in one parent email. Rows in a batch share `batch_number`
  (internal join key) and `response_token` (the unguessable URL key in the email buttons). One email
  covers every row sharing `(school_calendar_event_id, student_id, therapist_id)`. `schedule_id` is
  **not** part of the batch key, so two different sessions with the same therapist still batch together.
  If an event is multi day event and student has multiple schedules exists then we send 1 email.
- **Availability window** — a date+time range a therapist offers globally for make-ups. Stored in
  `schedule_makeup_availabilities` (UTC). Must not overlap the therapist's existing schedules.
- **Sub-slot** — the concrete time a parent picks inside a window. Duration = the missed session's
  length; start is free-form at 15-minute granularity. Booking a sub-slot **reschedules the missed
  `schedules` row in place** — there is no separate "booked slot" table; the booking *is* the schedule row.

## Lifecycle (status machine)

```
pending ──► sent ──┬─ Path 1: parent picks a sub-slot ───────────────────► scheduled
   │          │     │   (therapist HAS availability — self-reschedules in place, skips `requested`)
   │          │     │
   │          │     ├─ Path 2: parent accepts, therapist has NO availability ─► requested ─► scheduled
   │          │     │   (acceptance recorded, therapist notified, therapist books from their side)
   │          │     │
   │          │     └─► declined          (parent / therapist / auto-decline — whole batch)
   │          └─► declined
   └─► failed                             (email send failed)

(therapist may also mark a sent/requested row not_required)
```

`ScheduleMakeupRequestStatus`: `pending` · `sent` · `requested` · `declined` · `scheduled` ·
`failed` · `not_required`. Terminal: `declined`, `scheduled`, `failed`, `not_required`
(`isTerminal()`).

- **`requested`** means "parent accepted but booking not finalized" — reachable **only via Path 2**.
  Path 1 jumps `sent → scheduled` directly.
- There is **no `auto_declined` status**. System auto-declines collapse into `declined` and are
  distinguished by `responded_by_type = system` + `response_source = auto_declined`. "Show me all
  declines" stays a single-condition query.
- Once a make-up is `scheduled`, ongoing lifecycle (completion etc.) lives on the rescheduled/linked
  `schedules` row via its own `ScheduleStatus`, not here.

## Per-event configuration

Reminder/response dates are configured **per calendar event**, not from a global offset. The admin
Create/Edit Calendar Event form has an opt-in checkbox plus two dates:

| Form field | Column on `school_calendar_events` | Meaning |
|---|---|---|
| Request Makeup Session (checkbox) | `request_makeup` (bool, default false) | Opt-in. If off, no make-up rows are generated for the event. |
| Email Send Date | `reminder_date` (nullable) | When the reminder email is dispatched. |
| Response Date | `response_date` (nullable) | Shown to the parent as the response date **and** used as the auto-decline cutoff. |

Validation (Store + Update Form Requests):
- `request_makeup` boolean.
- When on: `reminder_date` and `response_date` are required; when off: both must be null (form clears them).
- Ordering: `reminder_date < response_date <= start_date`.

Events created before this feature, or with the checkbox off, have `request_makeup = false` and NULL
dates — the generator skips them. There is **no separate `deadline_date`**: `response_date` is the
single cutoff (the date shown to the parent and the auto-decline trigger).

`config/schedule_makeup.php`:
- `generator_lookahead_days` (15) — how far ahead the generator scans for closure events.
- `therapist_availability_reminder_offset_days` (3) — days before `reminder_date` to nudge a
  therapist who has no availability defined for the closure dates.

## Data model

### `schedule_makeup_requests`

One row per (closure event × scheduled session); carries the full lifecycle.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | NO | |
| `school_calendar_event_id` | FK → `school_calendar_events` (nullOnDelete) | YES | Nullable to future-proof non-closure make-ups |
| `schedule_id` | FK → `schedules` (cascadeOnDelete) | NO | The missed scheduled session |
| `student_id` | FK → `users` | NO | Student is a `User` row in this codebase; snapshot from the schedule |
| `therapist_id` | FK → `users` | NO | Snapshot — `schedules.sub_therapist_id` if set, else `therapist_id` |
| `event_date` | date | NO | The closure date (= `schedules.schedule_date`) |
| `reminder_date` | date | NO | Copied from the event |
| `response_date` | date | NO | Copied from the event; doubles as the auto-decline cutoff |
| `status` | string(20) → `ScheduleMakeupRequestStatus` | NO | Default `pending` |
| `batch_number` | string(32), indexed | NO | Canonical batch join key — shared across the batch |
| `reminder_sent_at` | datetime UTC | YES | Email-delivery timestamp |
| `responded_at` | datetime UTC | YES | When a response was recorded |
| `responded_by_type` | string(16) → `ScheduleMakeupRespondedByType` | YES | `parent` / `therapist` / `system` |
| `responded_by_user_id` | FK → `users` | YES | The actor; null only when `system` |
| `response_source` | string(32) → `ScheduleMakeupResponseSource` | YES | `email_link` / `therapist_manual` / `auto_declined` |
| `reason` | string | YES | Optional free-text decline reason |
| `makeup_schedule_id` | FK → `schedules` (nullOnDelete) | YES | The booked make-up session |
| `response_token` | string(64), indexed | NO | URL key in the email buttons — shared across the batch |
| `created_at` / `updated_at` / `deleted_at` | timestamps | | soft deletes |

**Indexes:** unique `(school_calendar_event_id, schedule_id)` (one row per missed occurrence);
`(therapist_id, status)`; `(reminder_date, status)`; `(response_date, status)`; `makeup_schedule_id`;
`response_token`; `batch_number`.

Frequency (weekly/monthly) is **not** stored — it is resolved at email-render time from
`schedule → ssa → frequency_type` to avoid drift.

**`responded_by_user_id` for parents:** even though the parent responds via the unauthenticated signed
link, the parent user is resolvable through `schedule → student → student_profile → parent_id`. The
response handler writes that id. It is null only for system auto-decline.

### `schedule_makeup_request_email_logs`

Dedicated email-log table (the generic `email_logs` consolidation with `schedule_email_logs` is a
follow-up, out of scope here). Columns: `schedule_makeup_request_id` FK, `type`
(`ScheduleMakeupEmailLogType`), `recipient_email`/`recipient_name`, `from_email`/`from_name`,
`subject`, `status` (`queued`/`sent`/`failed`), `sent_at`, `failed_at`, `error_message`, `metadata`
(json). Written by the sender and the therapist-notification service before/after each dispatch.

### `schedule_makeup_availabilities`

Global therapist make-up availability windows. Columns: `therapist_id` FK (restrictOnDelete),
`availability_date` (date), `start_time`/`end_time` (time, UTC), `notes`, timestamps, soft deletes;
index `(therapist_id, availability_date)`. `startUtc()`/`endUtc()` build Carbon instants mirroring
`Schedule`. A window may hold multiple non-overlapping sub-slot bookings from different students; it
expires implicitly once its date+time passes.

### `schedules` actor columns

Phase 8 added `created_by` and `updated_by` (nullable FK → `users`) to `schedules` so a make-up
booking records who made it — the parent (Path 1 self-reschedule), the therapist (Path 2 / manual),
or null/system for legacy rows.

## Jobs (Artisan commands, daily, idempotent)

Registered in `routes/console.php` (Laravel 12 — there is no `Console/Kernel.php`; commands are
auto-discovered from `app/Console/Commands/`).

| Command | Time | Does |
|---|---|---|
| `makeup-reminders:generate` | 03:00 | Scans closure events with `request_makeup = true` inside the lookahead window; runs the eligibility query against `schedules`; inserts missing rows as `pending` with `reminder_date`/`response_date` copied from the event and a minted `batch_number` + `response_token`. Idempotent via the unique `(event_id, schedule_id)` index. Picks up late-added schedules and closures entering the window. |
| `makeup-reminders:auto-decline` | 04:00 | Flips `sent` rows where `response_date < today` and `responded_at IS NULL` to `declined` (`responded_by_type = system`, `response_source = auto_declined`). Notifies the therapist for non-private students. |
| `makeup-reminders:therapist-availability` | 06:00 | `therapist_availability_reminder_offset_days` before `reminder_date`, emails therapists who have no availability windows for the closure dates. |
| `makeup-reminders:send-due` | 07:00 | Groups `pending`-due rows (`reminder_date <= today`) by `batch_number`, sends one `ScheduleMakeupReminderMail` per batch, writes email-log rows, flips the batch to `sent` / `failed`. Skipped batches (no parent email, no SSA frequency) stay `pending`. Per-batch try/catch. |

Dispatch mode is **A (fully automatic cron)**. Therapists can decline a `pending` row from their list
before the send fires; switching to a manual/opt-out mode later is just changing the caller.

### Batch identifiers

The generator keeps an in-memory `(student_id, effective_therapist_id) → {batch_number, response_token}`
map per event, **seeded from existing rows** so a later run adding a new occurrence under the same
student/therapist reuses the pair. `effective_therapist_id = sub_therapist_id ?? therapist_id`, so a
sub-covered day naturally lands in a different batch. Unseen pairs mint a fresh
`Str::random(32)` / `Str::random(64)`.

### Eligibility query

For a closure date, candidate schedules are `status = scheduled`, not soft-deleted, on `event_date`,
in the closure's school, that do not already have a row for this event. Prefer `whereHas` over raw
`whereExists` per the project conventions.

## Booking paths

The branch point is **whether the therapist has defined availability** for the affected date(s) when
the parent accepts (`ScheduleMakeupAvailabilityRepositoryInterface::therapistHasAvailabilityForDates()`).

**Path 1 — therapist HAS availability.** The parent public page renders the sub-slot picker
(`MakeupSlotCalculator`: "windows − schedules → valid 15-min starts" for the missed session's
duration). Booked time is never shown because booked make-ups are ordinary `schedules` rows and get
subtracted out. The parent picks one sub-slot **per affected day**. Commit is concurrency-safe: in a
single transaction, row-lock, **re-run `hasOverlap`** for the chosen sub-slot (therapist + student),
reschedule the missed `schedules` row in place, set `updated_by` to the parent's user id, link
`makeup_schedule_id`, flip the make-up row → `scheduled`. A lost race throws
`MakeupSlotConflictException` and re-prompts. Status is **per row** — multi-day partial completion is
allowed.

**Path 2 — therapist has NO availability.** Acceptance is recorded (`sent → requested`), and
`TherapistNoAvailabilityAcceptedMail` notifies the therapist. The therapist later books via their
"Book Make-Up" action: in-place reschedule of the existing missed row, or a new row if the original
was deleted; set `updated_by` to the therapist, link `makeup_schedule_id`, flip → `scheduled`.

**Decline** (either path) declines the **whole batch**.

## Public response endpoints

Unauthenticated, under `signed` + `throttle:10,1` (`routes/web.php`, prefix `makeup-response`):

- `GET /makeup-response/{token}/request` → `request` — resolves the batch by token, renders the
  slot-picker (Path 1) or an acceptance confirmation (Path 2).
- `POST /makeup-response/{token}/pick-slots` → `pickSlots` — commits the Path 1 sub-slot picks.
- `GET /makeup-response/{token}/decline` → `decline` — declines the whole batch.

Hardening: 256-bit `response_token`; Laravel `signed` middleware (tamper-proof URL); one-shot in
effect (a second click renders "already responded"); IP throttle; server rejects with a friendly page
when `response_date` has passed or `event_date` is in the past. Failure reasons are discriminated by
`MakeupResponseNotAllowedException` (`already_responded` / `deadline_passed` / `event_past` /
`bad_state`).

## Therapist UI

Therapist portal (`routes/therapist.php`, prefix `makeup-requests`):

- **Make-Up Requests** list — server-side DataTable scoped to the current therapist, filter by status.
- **Detail modal** (`show`) — status, parent response, decline reason; CTAs:
  - **Book Make-Up** (`book`, status `requested`) — GET redirect to schedule-create prefilled with
    `ssa_id`, `date`, `makeup_request_id`; `ScheduleController::store()` reads the hidden id and links
    the booked schedule (guards owning therapist + `requested` + not already linked).
  - **Decline** (`decline`) — manual decline on the parent's behalf (`responded_by_type = therapist`).
  - **Mark Not Required** (`not-required`).
- **Availability editor** (prefix `availability`) — add/remove date+time windows
  (`StoreMakeupAvailabilityRequest`, validated by `NoMakeupAvailabilityScheduleOverlap`); shows which
  sub-slots are already booked (derived from `schedules`, not stored).

Authorization: `ScheduleMakeupRequestPolicy` and `ScheduleMakeupAvailabilityPolicy` (therapist may
only act on their own rows; admin on all).

## Deletion guard

Deleting a `schedules` row that has a make-up request in `sent`, `requested`, or `scheduled`
(`ScheduleMakeupRequestStatus::blockingScheduleDeletionValues()` / scope
`blockingScheduleDeletion()`) is blocked in `ScheduleObserver::deleting()` — a single chokepoint
covering all service delete paths and the recurring cascade (force-deletes skipped). It throws
`CannotDeleteScheduleWithMakeupException`, caught in `ScheduleController::destroy()` /
`destroyFutureRecurring()` / `removeStudent()` → 422.

## Therapist notification emails

Five mailables in `App\Mail\ScheduleMakeup\`, templates in `resources/views/emails/makeup/`, all sent
through `TherapistMakeupNotificationService` (try/catch, side-effect swallowing). Subjects use
`MakeupRequestPresenter::firstInitialLastName()` ("Carmen DiMarzio" → "C. DiMarzio"). Each has a
`ScheduleMakeupEmailLogType` case.

| Mailable | When | Audience note |
|---|---|---|
| `TherapistAvailabilityReminderMail` | 3 days before `reminder_date`, no windows defined | — |
| `TherapistNoAvailabilityAcceptedMail` | Parent accepts, Path 2 | — |
| `TherapistDeclinedNotificationMail` | Parent declines | Non-private students only |
| `TherapistMakeupScheduledMail` | After a successful Path 1 booking | — |
| `TherapistNonAcceptedNotificationMail` | After bulk auto-decline | Non-private students only |

The parent reminder (`ScheduleMakeupReminderMail`) has weekly and monthly variants selected by
frequency (WEEKLY/BI_WEEKLY → weekly; MONTHLY/QUARTERLY/ONE_TIME → monthly), with signed
Request/Decline URLs.

## Architecture (DDD)

- **Models:** `ScheduleMakeupRequest` (`HasAudits`, scopes, status helpers),
  `ScheduleMakeupRequestEmailLog`, `ScheduleMakeupAvailability`.
- **Enums:** `ScheduleMakeupRequestStatus`, `ScheduleMakeupRespondedByType`,
  `ScheduleMakeupResponseSource`, `ScheduleMakeupEmailLogStatus`, `ScheduleMakeupEmailLogType`.
- **Repositories** (`App\Domain\Schedule\Makeup\Repositories`): `ScheduleMakeupRequestRepositoryInterface`,
  `ScheduleMakeupAvailabilityRepositoryInterface` (+ Eloquent implementations, bound in `AppServiceProvider`).
- **Services** (`App\Domain\Schedule\Makeup\Services`): `ScheduleMakeupReminderGenerator`,
  `ScheduleMakeupReminderSender`, `ScheduleMakeupResponseService`, `MakeupBookingService`,
  `MakeupSlotCalculator`, `ScheduleMakeupAvailabilityService`, `TherapistMakeupNotificationService`,
  plus `MakeupSlotConflictException` and the `MakeupRequestPresenter`.
- **DTOs** (`App\DTOs\Schedule\Makeup`): `CreateMakeupRequestDTO`, `GenerateMakeupRemindersDTO`,
  `RecordMakeupResponseDTO`, `MakeupSlotPickDTO`, `StoreMakeupAvailabilityDTO`.
- **Controllers:** `Public\ScheduleMakeupResponseController`, `Therapist\MakeupRequestController`,
  `Therapist\MakeupAvailabilityController`.
- **Form Requests:** `DeclineMakeupRequest`, `MarkNotRequiredMakeupRequest`, `StoreMakeupAvailabilityRequest`.
- **Policies:** `ScheduleMakeupRequestPolicy`, `ScheduleMakeupAvailabilityPolicy`.
- **Observer:** `SchoolCalendarEventObserver` soft-deletes `pending` rows on closure delete (edit
  handling deferred — see below). `ScheduleObserver::deleting()` enforces the deletion guard.

Audit: `HasAudits` on `ScheduleMakeupRequest` covers all status transitions. No ledger writes
(non-financial domain).

## Tests

Pest coverage for this domain (PHPStan analyses `app/` only, so test-file `$this` binding warnings
are expected and not gated).

**Unit** (`tests/Unit/`):

| File | Covers |
|---|---|
| `Domain/Schedule/Makeup/MakeupSlotCalculatorTest.php` | The "windows − schedules → valid 15-min starts" algorithm: window merge, busy-interval subtraction, alignment, fragmentation, duration-fit |
| `Domain/Schedule/Makeup/ScheduleMakeupEnumsTest.php` | The five enums — terminal/blocking value sets, labels, mappings |
| `Domain/Schedule/Makeup/ScheduleMakeupDTOsTest.php` | DTO `fromArray`/`fromRequest`/`toArray` round-trips |
| `Domain/Schedule/Makeup/ScheduleMakeupRequestModelTest.php` | Request model status helpers, `isTerminal()`, relations |
| `Domain/Schedule/Makeup/ScheduleMakeupRequestScopeTest.php` | `blockingScheduleDeletion()` and related scopes |
| `Domain/Schedule/Makeup/ScheduleMakeupAvailabilityModelTest.php` | `startUtc()`/`endUtc()`, scopes |
| `Domain/Schedule/Makeup/ScheduleMakeupResponseServiceTest.php` | `guardCanRespond` (all four `MakeupResponseNotAllowedException` reasons), response recording |
| `Domain/Schedule/Makeup/ScheduleMakeupAvailabilityServiceTest.php` | UTC conversion on create (incl. day-boundary), soft delete |
| `Domain/Schedule/Makeup/TherapistMakeupNotificationServiceTest.php` | All five mailables, private-student gating, email-log row status |
| `Domain/Schedule/Makeup/MakeupRequestPresenterTest.php` | `firstInitialLastName()` formatting |
| `Infrastructure/Repositories/EloquentScheduleMakeupAvailabilityRepositoryTest.php` | The booked-slot overlap queries — `schedulesOverlappingWindow` / `busySchedulesForWindows` (half-open edges, status + ownership filtering, multi-window union), `therapistHasAvailabilityFromDate` |
| `DataTables/Transformers/MakeupAvailabilityRowTransformerTest.php` | Availability-row rendering: booked sub-slots, timezone conversion, ordering |

**Feature** (`tests/Feature/`):

| File | Covers |
|---|---|
| `Console/MakeupRemindersGenerateTest.php` | The `makeup-reminders:generate` command — eligibility, idempotency, batch identifiers |
| `Console/MakeupRemindersSendDueTest.php` | `makeup-reminders:send-due` — batching, email logs, status flips, skip conditions |
| `Console/MakeupRemindersAutoDeclineTest.php` | `makeup-reminders:auto-decline` — system decline + therapist notification |
| `Public/ScheduleMakeupResponseControllerTest.php` | Signed response endpoints — request/pick-slots/decline, deadline/event-past/already-responded handling |
| `Therapist/MakeupRequestControllerTest.php` | List/detail, decline, mark-not-required, authorization |
| `Therapist/MakeupBookingTest.php` | Path 2 booking redirect (in-place vs. create fallback), `makeup_request_id` linking, validation |
| `Therapist/MakeupAvailabilityControllerTest.php` | Availability editor CRUD, validation, owner/role authorization |
| `Schedule/ScheduleMakeupDeletionGuardTest.php` | The `ScheduleObserver` deletion guard for sent/requested/scheduled requests, plus force-delete bypass |

**Still owed:**
- **Dusk** — therapist list/detail/booking, availability editor, parent landing pages and the
  sub-slot picker (no browser tests yet).
- **Path 1 concurrency race** — the lost-race `MakeupSlotConflictException` on simultaneous sub-slot
  commits is not yet simulated at the integration level.

`make qa` is gated on the `--min=80` coverage threshold.

## Open / deferred

- **Closure-event edit handling** — what should happen to existing `pending` rows when an admin
  changes a calendar event's dates/type/school is unresolved; the observer handles delete only.
- **Final make-up email copy** — placeholder wording remains in the weekly/monthly parent templates
  and the reminder subject line.
- **Generic `email_logs` table** — consolidating `schedule_email_logs` +
  `schedule_makeup_request_email_logs` is a tracked follow-up.

## Out of scope

- Non-closure make-up reasons (illness, ad-hoc) — schema supports (`school_calendar_event_id` nullable),
  no flow built.
- SMS / in-app notifications — email only.
- Buffer time between back-to-back make-up sessions.
