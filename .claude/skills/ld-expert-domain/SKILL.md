---
name: ld-expert-domain
description: Domain knowledge index for LD Expert Bird. Points to wiki PRD locations by role and area. Use before writing test scenarios, assertions, or review comments so business rules come from the source of truth. Triggers on "domain rules", "business logic", "ld expert domain", "where is X documented".
disable-model-invocation: true
---

# LD Expert Bird — Domain Knowledge Index

Domain knowledge lives in `app/wiki/`. Never re-write it here — read from the source.

---

## Wiki locations by role

| Role / Area | Read these files |
|---|---|
| **Admin — all** | `app/wiki/admin/*.md` |
| Admin — schools | `app/wiki/admin/schools.md` |
| Admin — students | `app/wiki/admin/students.md` |
| Admin — therapists | `app/wiki/admin/therapists.md` |
| Admin — SSA | `app/wiki/admin/ssa.md` |
| Admin — session logs | `app/wiki/admin/session-logs.md` |
| Admin — contracts | `app/wiki/admin/contracts.md` |
| Admin — schedule calendar | `app/wiki/admin/schedule-calendar.md` |
| Admin — student import | `app/wiki/admin/student-import.md` |
| Admin — reports | `app/wiki/admin/reports.md` |
| Admin — services | `app/wiki/admin/services.md` |
| Admin — settings | `app/wiki/admin/settings.md` |
| Admin — dashboard | `app/wiki/admin/dashboard.md` |
| Admin — leads | `app/wiki/admin/leads.md` |
| Admin — notifications | `app/wiki/admin/notifications.md` |
| Admin — student documents | `app/wiki/admin/student-documents.md` |
| Admin — analytics | `app/wiki/admin/analytics.md` |
| Admin — schedule reminders | `app/wiki/admin/schedule-reminders.md` |
| **Finance — all** | `app/wiki/finance/*.md` |
| Finance — invoicing | `app/wiki/finance/invoicing.md` |
| Finance — billing | `app/wiki/finance/billing.md` |
| Finance — payments | `app/wiki/finance/payments.md` |
| Finance — ledger | `app/wiki/finance/ledger.md` |
| Finance — expenses | `app/wiki/finance/expenses.md` |
| Finance — pay stub report | `app/wiki/finance/pay-stub-report.md` |
| Finance — billing automation | `app/wiki/finance/billing-automation.md` |
| Finance — accounting | `app/wiki/finance/accounting.md` |
| Finance — sync | `app/wiki/finance/sync.md` |
| **Therapist — all** | `app/wiki/therapist/*.md` |
| Therapist — workspace | `app/wiki/therapist/workspace.md` |
| Therapist — session logs | `app/wiki/therapist/session-logs.md` |
| Therapist — student comments | `app/wiki/therapist/student-comments.md` |
| Therapist — sub coverage | `app/wiki/therapist/sub-coverage.md` |
| Therapist — menu | `app/wiki/therapist/menu.md` |
| **Student — all** | `app/wiki/student/*.md` |
| Student — portal | `app/wiki/student/portal.md` |
| Student — menu | `app/wiki/student/menu.md` |
| **Cross-cutting** | `app/wiki/email-notifications.md`, `app/wiki/integrations.md`, `app/wiki/operations.md` |

---

## Role → dashboard route map

| Role | Login redirects to | Route prefix |
|---|---|---|
| Admin | `/admin/dashboard` | `admin.*` |
| Therapist | `/therapist/dashboard` | `therapist.*` |
| Student | `/student/dashboard` | `student.*` |

> **Finance is a module, not a role.** All finance functionality (`/admin/invoices/*`, `/admin/billing/*`, `/admin/payments/*`, `/admin/ledger/*`, `/admin/expenses/*`) is part of the Admin role. There is no `Role::FINANCE` enum and no `User::factory()->finance()` state. Finance QA tests use `User::factory()->admin()->create()`. See `app/wiki/finance/accounting.md` — the access control note reads: *"If a Finance role does not exist yet, these permissions apply to Admin users."*

---

## Key status lifecycles

### Session log
`DRAFT` → `SUBMITTED` → `APPROVED` or `SENT_BACK` → (if SENT_BACK) `SUBMITTED` again

- Therapist can submit only from `DRAFT` or `SENT_BACK`
- Admin can approve or send back only from `SUBMITTED`
- Student sees session logs only when `APPROVED`

### Invoice
`DRAFT` → `SENT` → `PAID`

- Send action only available on `DRAFT`
- Record Payment only available on `SENT`
- Every payment creates a `ledger_entries` row

### Therapist Bill
`DRAFT` → `SENT` → `PAID`

- Same lifecycle as Invoice
- Every payment creates a `ledger_entries` row
- `php artisan ledger:verify` must pass after any payment

---

## Key data relationships (for factory chains)

When writing factories, always create records in this order:

```
School
  └── User (admin)
  └── User (therapist) → TherapistProfile → [manager: User]
  └── User (student)  → StudentProfile → [school_id]
                           └── SSA [school_id, assigned_therapist_id]
                                └── SsaGoal
                                └── SsaService
                           └── Schedule [therapist_id, student_id, ssa_id]
                           └── SessionLog [therapist_id, student_id, ssa_id]
Invoice [school_id]
  └── InvoicePayment → LedgerEntry
TherapistBill [therapist_id]
  └── TherapistBillPayment → LedgerEntry
```

---

## Role isolation rules

Each role MUST be blocked from other roles' routes:

| Trying to access | Expected result |
|---|---|
| Student → `/admin/*` | Redirect (not 200) |
| Student → `/therapist/*` | Redirect (not 200) |
| Therapist → `/admin/*` | Redirect (not 200) |
| Therapist → `/student/*` | Redirect (not 200) |
| Admin → `/student/*` | Redirect (not 200) |
| Admin → `/therapist/*` | Redirect (not 200) |

---

## Timezone rules (MANDATORY for assertions)

- All timestamps stored in **UTC** in the database
- Display converted to **viewer's timezone** via `UserTimezoneService`
- Viewer timezone resolution: admin → `users.timezone`, therapist → `therapist_profiles.timezone`, student → `student_profiles.timezone`
- Format times with `config('display.time')`, datetimes with `config('display.datetime')`
- **Never hardcode format strings** like `'Y-m-d H:i'` in assertions

---

## Soft delete rule

All major models use soft deletes. Deleted records must:
- Not appear in any list
- Return `assertSoftDeleted` in tests (never `assertDatabaseMissing`)

---

## UI selector reference

See `ld-expert-domain/ui-selectors.md` for all CSS IDs, Dusk attributes, table IDs, modal IDs, and form action routes mapped to their Blade files.
