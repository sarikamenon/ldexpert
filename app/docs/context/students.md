# Context — Students

Glossary of the domain language around student records, the people attached to them, and
duplicate detection. Terms are canonical; if code or a plan uses a word differently, that
is a smell to resolve, not a synonym to accept.

## Language

**Student**:
A child enrolled for services, represented by a `users` row (role `student`) paired with a
`student_profiles` row. The profile holds first/middle/last name, school/family, date of
birth, and grade.

**School / Family**:
The `schools` row a student belongs to (`student_profiles.school_id`). A "private student /
family" is a `schools` row flagged `is_private_student` — an individual household rather
than an institution. The form labels this field "School/Family"; treat **School** and
**Family** as the same entity, distinguished only by the private flag.
_Avoid_: organization, account.

**Parent email**:
The email registered on a student (`users.email`) — typically the **parent's**, not the
student's own, because students often have none. Shared by design across siblings, so it is
**non-unique** and is **not** a duplicate signal. A shared email indicates a Sibling, not a
Duplicate.

**Sibling**:
Two students who share a Parent email (and usually a School/Family) but have **different
names**. Siblings are legitimate, distinct students — explicitly **not** duplicates. The
name gate exists to avoid flagging them.

**Duplicate** (possible):
An existing student whose **first name and last name both match** a student being created
or edited (case-insensitive, accent-folded). This is the *only* trigger for a duplicate
warning. Date of birth, school, grade, and email are shown as context but never trigger it.
A "duplicate" is a warning, not a verdict — the admin confirms and may create anyway.
_Avoid_: match (as a noun for the trigger), namesake (a duplicate may be a genuine
namesake; the system does not distinguish them).

**Name gate**:
The rule that a duplicate warning fires **only** when first name + last name both match. The
single trigger condition for duplicate detection.
