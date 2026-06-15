# LD Expert Bird — UI Selectors Reference

All selectors sourced directly from `resources/views/`. Verify against Blade source before using in tests.

---

## Dusk attributes (`dusk="..."`)

These are the safest selectors for Dusk tests — use them over CSS classes.

### Auth
| Selector | Element | Blade file |
|---|---|---|
| `dusk="login-button"` | Login submit button | `resources/views/auth/login.blade.php` |

### Admin — Students
| Selector | Element | Blade file |
|---|---|---|
| `dusk="edit-student-{id}"` | Edit student button (dynamic) | `resources/views/admin/students/` |
| `dusk="student-status-toggle-{id}"` | Activate/deactivate toggle (dynamic) | `resources/views/admin/students/` |
| `dusk="student-first-name"` | First name input | `resources/views/admin/students/` |
| `dusk="student-last-name"` | Last name input | `resources/views/admin/students/` |
| `dusk="student-username"` | Username input | `resources/views/admin/students/` |
| `dusk="student-email"` | Email input | `resources/views/admin/students/` |
| `dusk="student-date-of-birth"` | Date of birth input | `resources/views/admin/students/` |

### Admin — Therapists
| Selector | Element | Blade file |
|---|---|---|
| `dusk="view-therapist-{id}"` | View therapist link (dynamic) | `resources/views/admin/therapists/` |
| `dusk="edit-therapist-{id}"` | Edit therapist button (dynamic) | `resources/views/admin/therapists/` |
| `dusk="status-toggle-{id}"` | Activate/deactivate toggle (dynamic) | `resources/views/admin/therapists/` |
| `dusk="therapist-first-name"` | First name input | `resources/views/admin/therapists/` |
| `dusk="therapist-last-name"` | Last name input | `resources/views/admin/therapists/` |
| `dusk="therapist-personal-email"` | Personal email input | `resources/views/admin/therapists/` |
| `dusk="therapist-phone"` | Phone input | `resources/views/admin/therapists/` |
| `dusk="therapist-ld-email"` | LD email input | `resources/views/admin/therapists/` |
| `dusk="therapist-llc-name"` | LLC name input | `resources/views/admin/therapists/` |
| `dusk="therapist-max-weekly-hours"` | Max weekly hours input | `resources/views/admin/therapists/` |
| `dusk="therapist-hourly-rate"` | Hourly rate input | `resources/views/admin/therapists/` |

---

## DataTable IDs

Wait pattern: `->waitFor('#{id} tbody tr')`  
Processing wait: `->waitUntilMissing('.dataTables_processing')`

### Admin tables
| Table ID | Page | Blade file |
|---|---|---|
| `studentsTable` | Students list | `resources/views/admin/students/index.blade.php` |
| `therapistsTable` | Therapists list | `resources/views/admin/therapists/index.blade.php` |
| `schoolsTable` | Schools list | `resources/views/admin/schools/index.blade.php` |
| `sessionLogsTable` | Session logs | `resources/views/admin/session-logs/index.blade.php` |
| `ssasTable` | SSAs list | `resources/views/admin/ssas/` |
| `invoicesTable` | Invoices list | `resources/views/admin/invoices/index.blade.php` |
| `invoicePaymentsTable` | Invoice payments | `resources/views/admin/payments/invoice-payments/index.blade.php` |
| `therapistBillPaymentsTable` | Bill payments | `resources/views/admin/payments/therapist-bill-payments/index.blade.php` |
| `ledgerAccountsTable` | Ledger accounts | `resources/views/admin/ledger/accounts/index.blade.php` |
| `leadsTable` | Leads list | `resources/views/admin/leads/index.blade.php` |
| `expensesTable` | Expenses | `resources/views/admin/expenses/index.blade.php` |
| `servicesTable` | Services | `resources/views/admin/services/index.blade.php` |
| `serviceAliasesTable` | Service aliases | `resources/views/admin/service-aliases/index.blade.php` |
| `positionsTable` | Positions | `resources/views/admin/positions/index.blade.php` |
| `expirationTable` | SSA expirations report | `resources/views/admin/reports/ssa/expirations.blade.php` |
| `utilizationTable` | SSA utilization report | `resources/views/admin/reports/ssa/utilization.blade.php` |
| `caseloadTherapistTable` | Caseload (therapist) | `resources/views/admin/reports/ssa/caseload.blade.php` |
| `caseloadUnassignedTable` | Caseload (unassigned) | `resources/views/admin/reports/ssa/caseload.blade.php` |
| `payStubTable` | Pay stub report | `resources/views/admin/finance/pay-stub-report/index.blade.php` |

### Therapist tables
| Table ID | Page | Blade file |
|---|---|---|
| `therapistSessionLogsTable` | Session logs | `resources/views/therapist/session-logs/index.blade.php` |
| `subRequestsTable` | Sub requests | `resources/views/therapist/sub-requests/index.blade.php` |
| `therapistQglobRequestsTable` | Qglob requests | `resources/views/therapist/qglob-requests/index.blade.php` |

---

## Modal IDs

Wait pattern: `->waitFor('#{id}')` then interact with elements inside.

| Modal ID | Trigger | Blade file |
|---|---|---|
| `assignTherapistModal` | Assign therapist to SSA | `resources/views/admin/ssas/` |
| `unassignTherapistModal` | Unassign therapist from SSA | `resources/views/admin/ssas/` |
| `ssaSelectionModal` | Select SSA for schedule | `resources/views/admin/billing/schedules/` |
| `creditNoteModal` | Add credit note to ledger | `resources/views/admin/ledger/` |
| `refundModal` | Add refund to ledger | `resources/views/admin/ledger/` |
| `editAdjustmentModal` | Edit ledger adjustment | `resources/views/admin/ledger/` |
| `rowDataModal` | View row detail | Various |
| `scheduleDetailsModal` | View schedule detail | `resources/views/admin/schedule-calendar/` |

### Modal confirmation buttons
| Button ID | Modal | Action |
|---|---|---|
| `assignModalConfirm` | `assignTherapistModal` | Confirm assign therapist |
| `unassignModalConfirm` | `unassignTherapistModal` | Confirm unassign therapist |

---

## SweetAlert2 selectors

SweetAlert2 renders outside the page DOM. Always wait before interacting.

| Selector | Usage |
|---|---|
| `.swal2-container` | Wait for dialog to appear: `->waitFor('.swal2-container')` |
| `.swal2-title` | Assert dialog title text |
| `.swal2-html-container` | Assert dialog body text |
| `.swal2-input` | Type into reason/input field: `->type('.swal2-input', 'reason')` |
| `button.swal2-confirm` | Click confirm: `->click('button.swal2-confirm')` |
| `button.swal2-cancel` | Click cancel: `->click('button.swal2-cancel')` |

---

## Form IDs

| Form ID | Purpose | Blade file |
|---|---|---|
| `createInvoiceForm` | Create new invoice | `resources/views/admin/invoices/` |
| `createBillForm` | Create new therapist bill | `resources/views/admin/billing/therapist-bills/` |
| `editScheduleForm` | Edit schedule | `resources/views/admin/billing/schedules/` |
| `filterForm` | Generic filter form | Various |
| `invoicePaymentsFiltersForm` | Filter invoice payments | `resources/views/admin/payments/invoice-payments/` |
| `therapistBillPaymentsFiltersForm` | Filter bill payments | `resources/views/admin/payments/therapist-bill-payments/` |
| `scheduleFiltersForm` | Filter schedules | `resources/views/admin/billing/schedules/` |
| `ssaFiltersForm` | Filter SSAs | `resources/views/admin/ssas/` |
| `payStubFiltersForm` | Filter pay stub | `resources/views/admin/finance/pay-stub-report/` |
| `utilizationFiltersForm` | Filter utilization report | `resources/views/admin/reports/ssa/utilization.blade.php` |
| `expirationFiltersForm` | Filter expiration report | `resources/views/admin/reports/ssa/expirations.blade.php` |
| `caseloadFiltersForm` | Filter caseload report | `resources/views/admin/reports/ssa/caseload.blade.php` |
| `session-log-document-upload-form` | Upload session log document | `resources/views/` |
| `document-upload-form` | Upload student document | `resources/views/admin/students/` |

---

## Key button IDs

| Button ID | Action |
|---|---|
| `calendarEventSubmit` | Submit calendar event |
| `addScheduleButton` | Open add schedule form |
| `updateSessionsBtn` | Bulk update sessions |
| `deleteLeadBtn` | Delete lead (triggers SweetAlert) |
| `submitNoteBtn` | Submit therapist note |
| `submit-document-btn` | Upload document |
| `submit-comment-btn` | Submit student comment |
| `save_invitees_btn` | Save calendar invitees |
| `applyCalendarFilters` | Apply schedule calendar filters |
| `clearCalendarFilters` | Clear schedule calendar filters |
| `selectAllBtn` | Select all rows |
| `deselectAllBtn` | Deselect all rows |

---

## Key form action routes

### Admin — session logs
| Route name | Method | Action |
|---|---|---|
| `admin.session-logs.approve` | POST | Approve session log (SUBMITTED → APPROVED) |
| `admin.session-logs.send-back` | POST | Send back session log (SUBMITTED → SENT_BACK) |
| `admin.session-logs.cancel` | POST | Cancel session log |
| `admin.session-logs.update` | PUT/PATCH | Edit session log |

### Admin — invoices
| Route name | Method | Action |
|---|---|---|
| `admin.invoices.store` | POST | Create invoice |
| `admin.invoices.send` | POST | Send invoice (DRAFT → SENT) |
| `admin.invoices.resend-email` | POST | Resend invoice email |
| `admin.invoices.attach-sessions.store` | POST | Attach sessions to invoice |
| `admin.invoices.payments.store` | POST | Record invoice payment (SENT → PAID) |

### Admin — therapist bills
| Route name | Method | Action |
|---|---|---|
| `admin.billing.therapist-bills.store` | POST | Create therapist bill |
| `admin.billing.therapist-bills.send` | POST | Send bill (DRAFT → SENT) |
| `admin.billing.therapist-bills.destroy` | DELETE | Soft-delete bill |
| `admin.billing.therapist-bills.attach-sessions.store` | POST | Attach sessions to bill |

### Admin — schools / students / therapists
| Route name | Method | Action |
|---|---|---|
| `admin.schools.store` | POST | Create school |
| `admin.schools.update` | PUT/PATCH | Update school |
| `admin.students.store` | POST | Create student |
| `admin.students.update` | PUT/PATCH | Update student |
| `admin.students.import.store` | POST | CSV import students |
| `admin.students.comments.store` | POST | Add student comment |
| `admin.students.documents.store` | POST | Upload student document |
| `admin.therapists.store` | POST | Create therapist |
| `admin.therapists.update` | PUT/PATCH | Update therapist |

### Admin — SSA
| Route name | Method | Action |
|---|---|---|
| `admin.ssas.store` | POST | Create SSA |
| `admin.ssas.update` | PUT/PATCH | Update SSA |
| `admin.ssas.import.store` | POST | CSV import SSAs |

### Therapist
| Route name | Method | Action |
|---|---|---|
| `therapist.schedule.store` | POST | Create schedule |
| `therapist.schedule.update` | PUT/PATCH | Edit schedule |
| `therapist.session-logs.store` | POST | Create session log |
| `therapist.session-logs.update` | PUT/PATCH | Edit session log |
| `therapist.session-logs.submit` | POST | Submit session log (DRAFT → SUBMITTED) |
| `therapist.session-logs.comment` | POST | Add comment to session log |
| `therapist.students.comments.store` | POST | Add student comment |
| `therapist.students.documents.store` | POST | Upload student document |
| `therapist.qglob-requests.store` | POST | Create qglob request |
| `therapist.qglob-requests.destroy` | DELETE | Cancel qglob request |

---

## Calendar / chart element IDs

| ID | Purpose |
|---|---|
| `calendar` / `fullCalendar` | FullCalendar schedule view |
| `studentProgressChart` | Student goal progress chart |
| `therapistProgressChart` | Therapist goal progress chart |
| `schoolSsaChart` | School SSA distribution chart |
| `ssaDistributionChart` | SSA distribution chart |
| `utilizationTrendChart` | SSA utilization trend chart |
| `deliveryProgressChart` | Delivery progress chart |
| `billingStatusBanner` | Invoice/bill status banner |

---

## Layout component IDs

| ID | Purpose |
|---|---|
| `goals-filter-pills` | SSA goals filter pill buttons |
| `goals-ssa-list` | SSA goals list container |
| `comments-scroll` | Student comments scroll container |
| `comments-list` | Student comments list |
| `documents-list` | Student documents list |
