---
status: accepted
---

# Student duplicate detection is name-gated only; email is explicitly not a signal

When creating or editing a student we warn the admin if a possible duplicate already
exists. The check is **gated solely on first name + last name** (case-insensitive, trimmed,
accent-folded); only when both match do we warn. Email, date of birth, school, and grade
are shown in the warning as decision context but **never trigger it**. The warning is
advisory — the admin confirms via a dialog and can create anyway (never blocked).

The non-obvious part is that **email is deliberately excluded as a match signal**. A
student often has no email of their own, so the *parent's* email is registered, and one
parent can have multiple children. `users.email` is therefore non-unique for students (no
unique constraint, no index — the create form states "Email for notifications (can be
shared with siblings)"). A shared email is thus strong evidence of a **sibling**, the exact
false-positive we must not flag. Requiring first + last name as the gate eliminates the
sibling case, because siblings share the parent email but differ in name.

## Considered options

- **Weighted match score with a percentage and threshold (email as a top-weight signal).**
  Rejected. An additive score conflates "same name, different email" (likely
  duplicate/namesake) with "same email, different name" (a sibling — explicitly *not* a
  duplicate) at similar totals. It also adds weight-tuning, a threshold, and optional-field
  normalization with no gain over a boolean rule once name is the anchor.
- **Fuzzy / nickname / Levenshtein name matching.** Deferred. `Jon` vs `Jonathan` and
  typos need a curated nickname table to do well and produce a long false-positive tail.
  Out of scope for a warn-don't-block v1; username and id_number uniqueness remain
  backstops.
- **Apply the soft name check to bulk CSV import.** Rejected (see Consequences). Import is
  non-interactive, so the adjudication the feature depends on is impossible there.

## Consequences

- **Scope is create + edit forms only.** Bulk import keeps its existing *hard* duplicate
  check on username (system-wide) and id_number (per school) — real unique-key collisions
  — and does **not** gain the soft name check. There is no admin to adjudicate the
  namesake/sibling ambiguity during an import; hard-skipping name matches would silently
  drop legitimate namesakes (data loss), and a "created-but-suspect" status would need new
  schema + review UI nobody asked for.
- **Server-authoritative, submit-only.** The controller runs the check before create/update
  and redirects back with the matches; the admin acknowledges via a dialog and resubmits
  with a `duplicate_acknowledged` flag. No client-side-only gate, no AJAX endpoint —
  disabling JS cannot bypass it.
- **Editing excludes self** via `excludeUserId` so a student never matches itself.
- **Accent-folding / case-insensitivity** depend on the `student_profiles` name column
  collation; if the column is not a `_ci` collation the folding is done in PHP.
- **No index, by current decision.** `(last_name, first_name)` is intentionally unindexed
  at current student volume; revisit when volume grows.

See `_local_docs/student-duplicate-detection-plan.md` for the full implementation plan.
