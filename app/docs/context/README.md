# Context Map

Bounded contexts in this codebase and where their domain language lives. Each context file
in this folder is a glossary only — canonical terms, not implementation. System-wide
architectural decisions live in [../adr/](../adr/).

## Contexts

- [Billing & Invoicing](./billing.md) — billing schedules, therapist bills, school
  invoices, advance reconciliation, the ledger.
- [Students](./students.md) — student records, their relationship to schools/families and
  parents, and duplicate detection.
- [Services](./services.md) — the work types a therapist logs, their independent flags
  (direct/indirect, THO counting, billable), and how sessions count toward owed hours.
- [Scheduling & Recurrence](./scheduling.md) — schedules, recurring series and their
  anchor/occurrences, modified occurrences, additive end-date, and the
  occurrence-vs-future edit scope.

## Relationships

- **Students → Billing & Invoicing**: Billing generates School Invoices for the
  school/family a student belongs to; Billing references students by ID, it does not own
  student data.
- **Services → Billing & Invoicing**: a Service's `is_billable` flag governs whether it
  reaches invoicing; its THO/served minutes are a utilization metric only and feed no
  billing calculation.
