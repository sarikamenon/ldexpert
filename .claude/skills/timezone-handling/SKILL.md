---
name: timezone-handling
description: Store, convert, query, and display dates/times correctly in the NOVA Laravel project. Use when writing or reviewing any code that touches timestamps, schedules, session logs, date-range filters, or user-facing date/time display — and whenever a date appears off by one day or in the wrong timezone. Triggers on "timezone", "UTC", "date is off by a day", "session_date / schedule_date", "convert to user time", "date range filter", "store the time".
---

# Timezone & Date Handling

Apply NOVA's UTC-everywhere rules. Full reference: `app/docs/TIMEZONE_GUIDE.md`.
The non-negotiables also live in `CLAUDE.md` (§ Dates & Timezones).

## The one rule everything follows

**Store every instant in UTC. Convert to the relevant timezone only on read.
Never store user-local time.** All conversion goes through
`App\Domain\Time\UserTimezoneService` — never do TZ math by hand or in SQL.

## Service API (real signatures)

```php
$tz->resolveTimezone(?User $user, ?string $overrideTz = null): string
$tz->parseUserLocalToUtc(string $dateTime, ?User $user = null, ?string $overrideTz = null): CarbonInterface  // WRITE
$tz->toUserTimezone(CarbonInterface $utc, ?User $user = null, ?string $overrideTz = null): CarbonInterface     // READ
$tz->userDayUtcRange(string|CarbonInterface $localDate, ?User $user = null, ?string $overrideTz = null): array // [startUtc, endUtc]
$tz->convertSessionLocalToUtc(array $data, User $user): array  // session_date/start_time/end_time → UTC
```

- `viewerTimezone()` is **planned, not implemented** — do NOT call it. For the
  logged-in viewer, use `resolveTimezone($currentUser)`.

## Decision flow

1. **Writing a datetime?** → `parseUserLocalToUtc()`, store the UTC result.
2. **Displaying a datetime?** → `toUserTimezone()` with the viewer's user, then
   pre-format in the controller/transformer using `config('display.datetime')`.
3. **Filtering by a date range?** → convert local bounds to UTC with
   `userDayUtcRange()` BEFORE the `whereBetween`. Never compare a user-local
   date directly to a stored UTC date column.
4. **Adding a new date column?** → decide its flavor (below) and document it.

## The two date-column flavors (most common bug)

| Flavor | Examples | On read | On write |
|--------|----------|---------|----------|
| **Event date** (has a paired UTC instant) | `session_logs.session_date`, `schedules.schedule_date` | Derive display from the **converted datetime** (`start_time`/`recorded_at`), NOT the date column. Use the column only for UTC-level SQL filtering. | Written through the same UTC conversion as the time column. |
| **Pure calendar date** (no instant) | `recurrence_end_date`, `ssa.start_date`, contract dates | Show as stored. | Store as the user typed it. No conversion. |

> **Why event dates can't be TZ-shifted:** a 6 AM NYC session has
> `session_date = Apr 29` (UTC). Shifting that midnight-UTC date into NYC gives
> Apr 28 8 PM → displays as Apr 28, a day off. Derive from the datetime instead.

## Whose timezone for display (by role)

| Role | Source |
|------|--------|
| admin / school | `users.timezone` |
| therapist | `therapist_profiles.timezone` |
| student | `student_profiles.timezone` |

Resolve **once per request**, apply to every row. `users.timezone` mirrors the
profile; `resolveTimezone()` falls back to the profile when the user row is
empty or `'UTC'`. Any user create/update DTO exposing a timezone field MUST put
`timezone` in `toUserArray()`.

`Schedule::displayTimezone()` / `SessionLog::displayTimezone()` resolve the
**row owner's** TZ — reserved for queue jobs and emails. Never use them on
viewer-facing surfaces.

## Hard don'ts

- ❌ `CONVERT_TZ` in SQL — staging lacks named-zone tables. Convert in PHP/Carbon.
- ❌ TZ-shifting an event date column for display.
- ❌ `whereBetween('schedule_date', [$localStart, $localEnd])` with un-converted local bounds.
- ❌ Storing user-local time, or assuming DB date == user-local date.
- ❌ Calling `viewerTimezone()` (not implemented yet).

## Migrations that re-interpret data

Backfills (e.g. local-as-UTC → true UTC) MUST snapshot originals to a backup
table for reversibility. Pattern:
`2026_04_30_000001_backfill_schedules_utc_from_therapist_timezone.php`.

## Tests

Any new TZ-sensitive logic needs Pest coverage that crosses a day boundary
(e.g. a late-evening PT time landing on the next UTC date) — that's where bugs
hide. Cover both an east-of-UTC and a west-of-UTC timezone.
