# Schedule Make-Up — Therapist Availability & Parent Self-Reschedule

> **Status:** Agreed design, not yet implemented. This doc captures the agreed flow for the
> availability + parent-self-reschedule layer that sits on top of the existing make-up request
> feature (see `_local_docs/schedule-makeup-requests-plan.md` for the base feature). Once built,
> tested, and confirmed, this becomes the formal reference and the relevant parts of the
> `_local_docs/` plan are superseded.

## What changed from the base plan

The base make-up plan ended with the **therapist manually booking** the make-up after a parent
clicked "Request Make-Up", and treated any parent slot pick as a non-binding preference.

This layer replaces that with **global therapist availability** plus **parent self-reschedule**:

- The therapist defines available make-up hours **globally** (not per request) as date+time windows.
- When a parent accepts, they pick a concrete sub-slot from those windows themselves, and that pick
  **reschedules the existing missed `schedules` row in place** — it is binding, not a suggestion.
- The therapist no longer has to book in the common case; they only define availability. Therapist-side
  booking stays available for flexibility and for the no-availability fallback.

## Vocabulary

- **Availability window** — a date+time range the therapist offers for make-ups (e.g. 27 May, 5–7pm UTC).
  Stored in a new table. Must not overlap the therapist's existing schedules at definition time.
- **Sub-slot** — the concrete time a parent picks *inside* a window. Its **duration equals the missed
  session's length**; its start is free-form at **15-minute granularity** (4:00, 4:15, 4:30 …).
- **Booking** — committing a sub-slot. There is **no separate "booked slot" table**: the booking *is* the
  rescheduled `schedules` row (its new `schedule_date` / `start_time` / `end_time`). A booked make-up
  therefore counts as an ordinary schedule for all future overlap checks.

## Grounding in the existing schema

Verified facts the design relies on (so we reuse, not reinvent):

- `schedules` stores `schedule_date` (date), `start_time` (time), `end_time` (time), **all in UTC**
  (backfilled by `2026_04_30_000001_backfill_schedules_utc_from_therapist_timezone.php`).
  `Schedule::startUtc()` / `endUtc()` build Carbon instants from them; `endUtc()` already handles
  midnight crossing.
- **Overlap detection already exists**: `EloquentScheduleRepository::hasOverlap(User, OverlapCheckDTO,
  OverlapExclusionsDTO)` — compares raw `start_time`/`end_time` on the same `schedule_date`, excludes
  `CANCELLED`, matches therapist **or** student, and supports excluding a schedule id / recurring batch.
  The availability computation and the booking-commit guard **reuse this**.
- **Reschedule is in-place**: `ScheduleService::updateSchedule()` updates the same row, converting
  user-local → UTC via `UserTimezoneService::parseUserLocalToUtc()`, and re-runs overlap validation
  excluding the current row. Rescheduling does **not** by itself change `schedules.status`.
- `ScheduleStatus` = `scheduled` | `completed` | `cancelled`.
- `ScheduleMakeupRequestStatus` = `pending` | `sent` | `requested` | `declined` | `scheduled` |
  `failed` | `not_required`. Terminal: `declined`, `scheduled`, `failed`, `not_required`.
- Therapist timezone resolves via `UserTimezoneService::resolveTimezone()` (override → `users.timezone`
  → `therapist_profiles.timezone` → `student_profiles.timezone` → UTC).
- The existing therapist `book()` flow (`ScheduleController::store()` → `linkMakeupRequestSchedule()`)
  already links `makeup_schedule_id` and flips the make-up status to `scheduled`.

Two gaps this layer must close:

- **No `created_by` / `updated_by` on `schedules`** (only `timestamps()`), and `Schedule` does **not**
  use `HasAudits`. The requirement to "know who created/updated the schedule" (parent vs therapist vs
  system) is therefore **not** satisfied today — see § Actor tracking.

## The two booking paths

The branch point is **whether the therapist has defined availability** at the moment the parent accepts.

```
Path 1 — therapist HAS availability for the affected date(s):
  parent accepts ──► picks a sub-slot per affected day ──► reschedules each missed row in place
                ──► make-up status → SCHEDULED   (skips `requested`)

Path 2 — therapist has NOT defined availability:
  parent accepts ──► make-up status → REQUESTED  (acceptance recorded, nothing booked)
                ──► notification email to therapist: "parent accepted, no availability defined"
                ──► therapist schedules from their side ──► SCHEDULED
                       ├─ original schedule still exists → reschedule it in place
                       └─ original was deleted           → create a new schedule, link makeup_schedule_id
```

`requested` now means **"parent accepted but booking not finalized"** and is reachable only via Path 2.
"Which accepted make-ups still need the therapist to book?" = `status = requested`.

## Status machine (make-up request)

```
pending ──► sent ──┬─ Path 1: parent picks sub-slot ──────────────► scheduled
                   │
                   ├─ Path 2: parent accepts (no avail) ─► requested ─► (therapist books) ─► scheduled
                   │
                   └─ parent declines (either path) ─────────────────► declined   (whole batch)
   │
   └─► failed   (email send failed)
```

Per-row status (see multi-day below). `declined` applies to the **whole batch**. The `not_required`
status from the base feature is unchanged.

## Therapist availability

### Definition rules

- Availability is **global per therapist**, defined as date+time windows. Not tied to any request.
- A window is stored as **UTC** (parse therapist-local input via `parseUserLocalToUtc`), consistent with
  `schedules`.
- **No-overlap-at-definition:** a window may not overlap the therapist's existing schedules. Reuse the
  existing overlap logic (therapist-side) to validate. Example: therapist has a schedule 27 May 4–5pm;
  defining availability 27 May 4–7pm is rejected for the 4–5 portion — they must define 5–7pm.
- A window can absorb **multiple** sub-slot bookings from **different** students as long as they don't
  overlap (e.g. 5–6 to student X, 6–7 to student Y). Not one-booking-per-window.
- **Expiry is implicit**: a window (or the remaining part of it) is unusable once its date+time has
  passed. No status flag needed — the date comparison handles it.
- A booked make-up sub-slot feeds back into overlap checks as an ordinary schedule, so neither a new
  availability window nor another student can reuse that time (Q4).

### Computing what the parent can pick ("windows − schedules")

For a therapist + an affected date + a required duration (the missed session's length):

1. Load the therapist's availability windows for that date (UTC).
2. Load the therapist's existing schedules overlapping those windows (UTC) — this already includes booked
   make-ups, since they are ordinary schedule rows.
3. Subtract (2) from (1) → free intervals (standard interval subtraction).
4. Within each free interval, enumerate valid start times at **15-min steps** where
   `start + duration` still fits inside the free interval.

**Already-booked time is never shown to the parent.** Because booked make-ups are ordinary schedule rows,
step 2 subtracts them out: if the window is 4–7pm and 4–5pm is already booked, the parent only ever sees
5–7pm. We do not surface a therapist's unavailable (booked) time as a pickable option.

This is the **read** side and is simple: a small, well-tested interval helper. Gaps from fragmentation
are acceptable (first-come; no packing, no buffer between sessions).

### Booking commit (concurrency-safe)

Read-time subtraction (above) is what keeps booked time off the parent's screen. The concurrency guard
below is a *separate* concern: the offered list is stale the instant it renders (two parents can be
offered the same 5–6pm before either commits). So the commit must, **inside a single DB transaction**:

1. Row-lock the relevant schedule row(s).
2. **Re-run the overlap check** for the chosen sub-slot (reuse `hasOverlap`) against the therapist's
   current schedules.
3. If clear, write the new `schedule_date`/`start_time`/`end_time` (UTC) onto the existing missed
   `schedules` row (in-place reschedule) and flip the make-up status to `scheduled`.
4. If the slot was taken in the meantime, reject with a friendly "that time was just taken, please pick
   another" response.

No data-model trick removes this race — it is inherent to shared availability. The guard is the standard
"re-check under lock" pattern.

## Multi-day school closures

- A multi-day closure still produces **one email** for the batch (base-feature batching rule unchanged).
- After the parent accepts, show **all affected existing schedules** in the batch.
- The parent picks a sub-slot **per affected day** — each pick reschedules that day's missed row.
- **Status is per row.** Partial completion is allowed: some rows `scheduled`, others still `sent`. There
  is no aggregate batch status for booking progress (decline is the only batch-wide action).

## Decline

- "Decline Make-Up" still exists for the parent.
- A parent decline **declines the whole batch** (every row), regardless of path.

## Deletion guard

- Block deleting an existing `schedules` row while it has a make-up request in status `sent`,
  `requested`, or `scheduled`.
- If the therapist deletes a schedule through an allowed path, the Path 2 fallback applies: a later parent
  accept creates a **new** schedule (linked via `makeup_schedule_id`) instead of rescheduling.

## Actor tracking (who created/updated the schedule)

Requirement: distinguish whether a schedule was created/rescheduled by the **parent** (self-service),
the **therapist**, or the **system**.

Current state: `schedules` has no `created_by`/`updated_by` and does not use `HasAudits`.

**Decision: add three new columns to `schedules`:**

| Column | Type | Notes |
|---|---|---|
| `created_by` | nullable FK → `users.id` | Who created the row |
| `updated_by` | nullable FK → `users.id` | Who last updated the row (e.g. the rescheduler) |
| `created_source` / actor source | enum-ish string | `parent` \| `therapist` \| `system` — how the change was made |

(Exact column naming for the source discriminator is settled at build time; the intent is three columns:
two actor FKs + one source flag.)

The **parent self-reschedule path must stamp the actor** even though the parent acts via the
unauthenticated signed email link — the parent user is resolvable from `schedule → student → parent`, the
same way the base feature resolves `responded_by_user_id`. Existing rows backfill to `null` actor /
`system` (or unknown) source.

## `makeup_schedule_id`

Kept and populated. In the in-place reschedule case it points at the same row that was rescheduled; in the
Path 2 "original deleted → new schedule" case it points at the newly created row. Retained for the
"therapist created a new schedule" mapping and for reporting; may be revisited later if it proves
redundant.

## Open items

1. **Therapist notification email on `sent → requested`** (Path 2 only) — confirmed to be a **dedicated
   mailable**. Wording is finalized when the email template is written. The status change itself is
   confirmed and happens regardless.

Actor tracking is now decided (three new `schedules` columns — see § Actor tracking).

## Out of scope (unchanged from base plan)

- Make-up requests for non-closure reasons (illness, ad-hoc) — schema supports, no flow built.
- SMS / in-app notifications — email only.
- Buffer time between back-to-back sessions — none for now.
