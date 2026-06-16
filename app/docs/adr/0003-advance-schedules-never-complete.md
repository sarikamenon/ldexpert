---
status: accepted
---

# Advance billing depends on schedules never transitioning to COMPLETED

`ScheduleStatus` defines three values — `SCHEDULED`, `COMPLETED`, `CANCELLED` — but **no
code path ever sets a schedule to `COMPLETED`**. A schedule stays `SCHEDULED` for its
entire life, even after its session has been delivered, logged, and approved. Delivery
state lives on the `SessionLog` (and `session_logs.billing_status`), not on the parent
`Schedule.status`.

This is not an oversight to "fix" — three advance-billing surfaces lean on it, all via the
`scheduled()` scope (`status = 'scheduled'`):

1. **`AdvanceChargeLineBuilder::build`** (`scheduled()->notYetInvoiced()`) — the set a new
   advance invoice is built from.
2. **Advance invoice re-open / re-select** (`attachSchedulesToAdvanceDraft` →
   `AdvanceChargeLineBuilder`) — rebuilds the corrected invoice's charge lines. A kept,
   already-delivered schedule only round-trips back onto the re-opened invoice because it
   is still `SCHEDULED`. See [[Re-open]] in `docs/context/billing.md`. (Re-open is the
   domain verb used throughout the code; the user-facing button is labelled
   **"Edit Invoice"** — the term split is documented in the [[Re-open]] glossary entry.)
3. **`AdvanceReconciliation`** indirectly — it reads delivered `SessionLog`s and trues them
   against billed `invoice_line_items`; a schedule disappearing from `scheduled()` mid-flow
   would desync the billed-vs-delivered comparison.

The non-obvious, dangerous part: adding a natural-looking "mark schedule completed"
transition on session-log approval would **silently** break (1)–(3). A delivered schedule
would drop out of `scheduled()`, so re-saving a re-opened advance invoice would quietly
omit the delivered session's charge line — the school underpays and nothing errors. The
failure is invisible at write time and only shows as a wrong total.

## Considered options

- **Status quo — schedules never go COMPLETED; delivery state lives on `SessionLog`.**
  Accepted. The advance pipeline already treats "scheduled" as "this is a billing unit for
  its period," independent of whether the session has happened. `SessionLog` is the
  delivery record; `billing_status` is the billed record. Nothing needs a `COMPLETED`
  schedule status.
- **Transition schedule → COMPLETED on session-log approval.** Rejected. It reads natural
  but breaks the three surfaces above silently. If ever wanted, it must be paired with
  rewriting `scheduled()`'s callers to select billing units by "belongs to the period and
  not cancelled" rather than by raw status — a coordinated change, never a drop-in.
- **Drop `COMPLETED` from the enum to make the trap unrepresentable.** Deferred. Harmless
  today but a wider change than this billing concern warrants; the enum value may be wanted
  for non-billing display later. Documented here instead.

## Consequences

- **`scheduled()` means "is a billing unit for its period," not "hasn't happened yet."**
  Treat it as billing-domain language, not lifecycle truth.
- **To detect delivery, check the `SessionLog`** (`Schedule::sessionLog()` HasOne /
  `session_logs.billing_status`) — never `Schedule.status`.
- **Any future `COMPLETED` transition is a coordinated migration**, not a one-liner: every
  `scheduled()` caller in the advance pipeline must be re-expressed first.
- The advance invoice **re-open** feature's correctness for delivered-but-kept schedules
  rests on this ADR; if it is ever violated, re-open silently undercharges.
