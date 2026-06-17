# Finance Test Data

> Used by: qa/LD-Expert-QA.xlsx — Finance sheet (TC-F001–TC-F054)
> Generated: 2026-06-12. Run in Tinker: `docker compose exec -T app bash -lc 'php artisan tinker'`
>
> Finance is a module under the **Admin** role — log in as the seeded **system admin**
> `develop.ldexpert@gmail.com` / `Password123!` (admin users cannot be created). No school login.
> Every payment writes a `ledger_entries` row via `LedgerService`; run `php artisan ledger:verify` after.

---

## TD-F001: Approved session logs for one school — Invoices
**Used by:** Invoices, Invoice Payments cases

```php
use App\Enums\SessionLogStatus;

$admin   = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail(); // seeded system admin
$school  = School::factory()->create(['status' => \App\Enums\SchoolStatus::ACTIVE,
                                       'invoice_email' => 'billing@school.test']);
$therapist = User::factory()->therapist()->create();
TherapistProfile::factory()->for($therapist, 'user')->create();
$student = User::factory()->student()->create();
$student->studentProfile()->update(['school_id' => $school->id]);
$service = Service::factory()->create();
$ssa = ServiceSupportAgreement::factory()->active()->create([
    'student_id' => $student->id, 'primary_service_id' => $service->id,
    'assigned_therapist_id' => $therapist->id,
]);

// 3 APPROVED logs in the billing period, same school, with school_invoice_amount set
SessionLog::factory()->count(3)->create([
    'status' => SessionLogStatus::APPROVED, 'school_id' => $school->id,
    'student_id' => $student->id, 'therapist_id' => $therapist->id,
    'ssa_id' => $ssa->id, 'service_id' => $service->id,
    'session_date' => '2026-06-10', 'school_invoice_amount' => 120.00,
]);
// Negative case "different schools": create approved logs under a 2nd school too.
```

---

## TD-F002: Invoice in DRAFT / SENT — Invoices, Invoice Payments
**Used by:** Invoices (send), Invoice Payments cases

```php
use App\Enums\InvoiceStatus;

$draftInvoice = Invoice::factory()->create([
    'school_id' => $school->id, 'status' => InvoiceStatus::DRAFT,
]);
$sentInvoice  = Invoice::factory()->create([
    'school_id' => $school->id, 'status' => InvoiceStatus::SENT,
]);
// Record payment only allowed on SENT → creates a ledger entry via LedgerService.
```

---

## TD-F003: Therapist bill in DRAFT / SENT — Therapist Billing, Bill Payments
**Used by:** Therapist Billing, Bill Payments cases

```php
use App\Enums\TherapistBillStatus;

$draftBill = TherapistBill::factory()->create([
    'therapist_id' => $therapist->id, 'status' => TherapistBillStatus::DRAFT,
]);
$sentBill  = TherapistBill::factory()->create([
    'therapist_id' => $therapist->id, 'status' => TherapistBillStatus::SENT,
]);
```

---

## TD-F004: Ledger entries + adjustment — Ledger
**Used by:** Ledger cases

```php
// Entries are created through LedgerService (never LedgerEntry::create directly).
// A payment on $sentInvoice produces a source-document entry.
// A credit_note / refund adjustment is created via LedgerAdjustmentController@store
// (only credit_note and refund are editable/deletable from the ledger UI).
// After any backdated/edited/deleted entry: LedgerService::recomputeChainFrom().
```

---

## TD-F005: Expense + category — Expenses, Expense Categories
**Used by:** Expenses, Expense Categories cases

```php
$category = ExpenseCategory::factory()->create(['name' => 'QA Office']);
$expense  = Expense::factory()->create([
    'expense_category_id' => $category->id, 'amount' => 250.00,
    'expense_date' => '2026-06-01',
]);
```

---

## TD-F006: Billing schedule — Billing Schedules, Billing Settings, Entity Config
**Used by:** Billing Schedules, Billing Settings, Entity Billing Config cases

```php
use App\Enums\BillingScheduleType;
use App\Enums\BillingFrequency;
use App\Enums\BillingMode;

$schedule = BillingSchedule::factory()->create([
    'schedulable_type' => School::class, 'schedulable_id' => $school->id,
    'schedule_type'    => BillingScheduleType::SCHOOL_INVOICE,
    'frequency'        => BillingFrequency::WEEKLY,
    'billing_mode'     => BillingMode::STANDARD,
    'is_active'        => true, 'auto_generate' => true,
]);
// "Run Now" with approved sessions in the period → draft invoice + run logged (success).
// "Run Now" with no sessions → run status skipped_no_sessions.
```

---

## Reset between tests
- `QaDuskTestCase::tearDown()` removes `qa*` records.
- After payment cases: `php artisan ledger:verify` must pass.
- Full reset: `php artisan migrate:fresh --seed` against `bird_test`.
