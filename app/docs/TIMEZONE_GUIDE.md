# Timezone & Date Handling Guide

The authoritative reference for how this project stores, converts, and displays
dates and times. The mandatory rules live in `CLAUDE.md` (§ Dates & Timezones);
this document holds the reasoning, worked examples, and edge cases behind them.

> **Golden rule:** store every instant in UTC, convert to the relevant timezone
> only on read, and never store user-local time.

---

## 1. The conversion service

All conversions go through `App\Domain\Time\UserTimezoneService`:

| Method | Use for |
|--------|---------|
| `parseUserLocalToUtc(...)` | Writes — turn user-local input into a UTC instant before saving. |
| `toUserTimezone(...)` | Reads — render a stored UTC instant in the relevant timezone. |
| `resolveTimezone(?User $user, ?string $overrideTz = null)` | Look up a user's effective timezone. Falls back to the profile timezone if the `users` row is empty or UTC. |
| `userDayUtcRange(...)` | Convert a user-local **date range** into the UTC range to query against. |
| `convertSessionLocalToUtc(array $data, User $user)` | Session-specific local→UTC helper. |

Never do timezone math by hand or in SQL — always route through this service.

---

## 2. Whose timezone for display

Display DATETIMEs in the **logged-in viewer's** own timezone, resolved by role:

| Role | Source column |
|------|---------------|
| admin | `users.timezone` |
| therapist | `therapist_profiles.timezone` |
| student | `student_profiles.timezone` |
| school | `users.timezone` |

Resolve the viewer's timezone **once per request**, then apply it to every row in
the response.

### `viewerTimezone()` — PLANNED, not yet implemented

`UserTimezoneService::viewerTimezone()` is the intended single entry point for
per-viewer resolution, but it **does not exist yet** — do not call it. Until it
ships, use `resolveTimezone()` with the current user. Implementation plan:
[`_local_docs/viewer-timezone-display-plan.md`](../../_local_docs/viewer-timezone-display-plan.md).

### Row-owner timezone (different concern)

`Schedule::displayTimezone()` and `SessionLog::displayTimezone()` resolve the
**row owner's** timezone, not the viewer's. They are reserved for queue jobs and
emails that must render in the recipient's timezone. **Never use them for
viewer-facing surfaces.**

> **Current state:** existing surfaces still resolve per-row-owner timezone. The
> migration to per-viewer display is tracked in the plan above and is not yet
> complete — expect mixed behavior until then.

---

## 3. `users.timezone` mirrors the profile

`users.timezone` must always mirror the profile's timezone:

- Therapist DTOs write to **both** `users.timezone` and `therapist_profiles.timezone`.
- Student DTOs write to **both** `users.timezone` and `student_profiles.timezone`.
- Any user create/update DTO that exposes a timezone field MUST include
  `timezone` in `toUserArray()` so the two stay in sync.

`resolveTimezone()` falls back to the profile if the `users` row is empty/UTC.

---

## 4. Two date-column flavors — know the difference

There are two kinds of DATE columns and they behave **oppositely**. Confusing
them is the single most common timezone bug.

### Event dates (companions to a UTC datetime)

Examples: `session_logs.session_date`, `schedules.schedule_date`.

These are the **UTC calendar date** of an underlying instant
(`start_time` / `recorded_at`). They are written through the same UTC conversion
as the time column. They are **not** "school-local" or "therapist-local" dates.

**For display, always derive the date from the paired datetime
(`start_time` / `recorded_at`) converted to the relevant timezone — never from
`session_date` / `schedule_date` directly.**

> **Why:** TZ-shifting a DATE-only column moves the entire day boundary. A 6 AM
> NYC session has `session_date = Apr 29` in UTC. If you naively shift that
> midnight-UTC date to NYC, it becomes Apr 28 8 PM — and displays as **Apr 28**,
> a day off. Deriving the date from the converted datetime gives the correct
> Apr 29.

Use the event-date column **only for SQL filtering at the UTC level**. To filter
by a user-local date range, first convert that range to UTC via
`UserTimezoneService::userDayUtcRange()`.

### Pure calendar dates (no time companion)

Examples: `recurrence_end_date`, `ssa.start_date`, contract effective dates.

Stored exactly as the user typed them — they represent "the date in the
operating timezone" and have no specific UTC moment. **No conversion on read or
write.**

### When in doubt

Ask before adding a new date column. Decide up front whether it is an event date
(has a UTC instant) or a pure calendar date (no instant), and document it.

---

## 5. Querying by date — never assume DB date == local date

A late-evening session in Pacific Time stores as the **next-day** UTC date. So
`whereBetween('schedule_date', [$localStart, $localEnd])` with user-local bounds
is wrong — it will miss or include the wrong rows at the day boundary.

Always convert the user's local day to a UTC range first. `userDayUtcRange()`
takes **one** local date and returns the `[startUtc, endUtc]` bounds of that
local day in UTC:

```php
// Single day:
[$utcStart, $utcEnd] = $timezoneService->userDayUtcRange($localDate, $user);
Schedule::whereBetween('schedule_date', [$utcStart, $utcEnd])->get();

// Range spanning multiple days — take the start of the first day and the end of the last:
[$utcStart] = $timezoneService->userDayUtcRange($localStart, $user);
[, $utcEnd]  = $timezoneService->userDayUtcRange($localEnd, $user);
Schedule::whereBetween('schedule_date', [$utcStart, $utcEnd])->get();
```

---

## 6. Never use `CONVERT_TZ` in SQL

MySQL `CONVERT_TZ` is **not available in all environments** — staging lacks the
named-zone tables. Do every timezone conversion in PHP/Carbon, never in SQL.

---

## 7. Display formatting

Date/time display formatting is governed by
[`BLADE_GUIDELINES.md`](BLADE_GUIDELINES.md): pre-format in controllers, never in
Blade; use `config('display.time')` / `config('display.datetime')` for all
user-visible times. These rules apply to DataTable transformers, API Resources,
and mail templates too — not just Blade views.

---

## 8. Migrations that re-interpret existing data

Any migration that re-interprets stored data (e.g. backfilling from
local-as-UTC to true UTC) MUST snapshot original values to a backup table for
reversibility. Canonical pattern:
`2026_04_30_000001_backfill_schedules_utc_from_therapist_timezone.php`.
