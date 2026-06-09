# Context Map

Bounded contexts in this codebase and where their domain language lives. Each context file
in this folder is a glossary only — canonical terms, not implementation. System-wide
architectural decisions live in [../adr/](../adr/).

## Contexts

- [Billing & Invoicing](./billing.md) — billing schedules, therapist bills, school
  invoices, advance reconciliation, the ledger.
- [Students](./students.md) — student records, their relationship to schools/families and
  parents, and duplicate detection.

## Relationships

- **Students → Billing & Invoicing**: Billing generates School Invoices for the
  school/family a student belongs to; Billing references students by ID, it does not own
  student data.
