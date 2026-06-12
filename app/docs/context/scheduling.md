# Context — Scheduling & Recurrence

Glossary of the domain language around therapist schedules, recurring series, and
how an edit reaches one session vs the whole series. Terms are canonical; if code
or a plan uses a word differently, that is a smell to resolve, not a synonym to
accept.

## Schedule

A `schedules` row — one planned tutoring session for a therapist + student at a
date/time. May stand alone or belong to a recurring series.

## Single (schedule)

A standalone, non-recurring schedule: `recurrence_type = none` **and** no parent
(`parent_schedule_id IS NULL`) — the meaning enforced by `Schedule::scopeSingle()`.
"Single" describes a **schedule's shape**, never an edit action.
_Avoid_: using "single" to mean "edit one item" — that reach is an [[edit-scope]]
called **occurrence**, a different axis.

## Recurring schedule / Series

Schedules generated from one recurrence rule, linked by a shared
`recurring_batch_number`. `Schedule::scopeRecurring()` matches any schedule whose
`recurrence_type != none`.

## Anchor

The series' template row: `parent_schedule_id IS NULL`, carries
`recurring_batch_number`, `recurrence_type`, `recurrence_end_date`. Other
occurrences point at it via `parent_schedule_id`.

## Occurrence

One non-anchor session in a series (`parent_schedule_id` set). The unit a user
edits when they choose "this one only".

## Modified occurrence (exception)

An occurrence whose date or time differs from the series default but which
**stays in the series** (keeps `recurring_batch_number` and `parent_schedule_id`).
This is the iCalendar / Google Calendar model: editing one session creates an
exception, not a standalone schedule. The same row is mutated in place — id and
any linked session log are preserved.
_Avoid_: editing an occurrence into a standalone schedule, or
delete-and-recreate — an edited occurrence always remains a member of its series.
_Note_: a later **series**-level rebuild (recurrence-type change) regenerates the
whole batch and overwrites individual modifications — modifications are not
preserved across a series rebuild.

## Demote (a batch)

When a recurring batch is reduced to one remaining non-deleted occurrence, that
lone survivor is cleared of its recurrence linkage and becomes a **single**
schedule — a series of one is not a series. Triggered by deletions, never by an
edit. A billed survivor is left untouched.

## Re-anchor (a batch)

After deletions remove rows from a batch (possibly the anchor itself), the batch
is re-structured so exactly one anchor (`parent_schedule_id IS NULL`) remains and
every other row points at it — no row references one that has left the batch.

## Additive end-date change

Editing the **series' Recurrence End Date** is additive: extending creates only
the new dates beyond the old end; shrinking deletes only the rows past the new
end. It never rebuilds the series.

## Edit scope (occurrence vs series)

How far an edit reaches — distinct from a schedule's *shape* ("single"/"recurring"):
- **occurrence** — edit only this one schedule. Date/time changes apply to this
  session as a [[modified-occurrence]] (it stays in the series); notes/location
  changes likewise affect only this row. Siblings are never touched.
- **series** — edit the whole recurring series (the full recurrence editor:
  per-occurrence rows, recurrence end date).
