# Therapist Billing Implementation Plan

## Overview

Implement billing for approved session logs and send bills to therapists via email, similar to how invoicing works for schools.

## Compliance with Project Rules (.cursor/rules.md)

This implementation plan fully complies with all project rules:

### Architecture & Patterns ✅

-   **Monolith only**: No API controllers, only Blade pages with Form Requests
-   **DTOs**: All data transport uses DTOs (`CreateTherapistBillDTO`, `SendTherapistBillDTO`, `TherapistBillFilterDTO`)
-   **Form Requests**: All controllers type-hint Request objects from `app/Http/Requests/**`
-   **Service Layer**: Controllers delegate to Services; Services use Repositories
-   **Eloquent**: Prefer Eloquent ORM; no raw queries unless justified

### Code Quality ✅

-   **Use statements**: All imports use `use` statements (never fully qualified class names)
-   **PSR-12**: Follows PSR-12 coding standards
-   **File size**: Hard cap of 300 lines per file - plan explicitly mentions extracting when needed
-   **Soft deletes**: All models use soft deletes (`deleted_at` column and `SoftDeletes` trait)

### Testing ✅

-   **Unit tests**: For DTOs, Services, Repositories
-   **Feature tests**: For routes/controllers
-   **Dusk tests**: For UI (views, forms, interactions)
-   **No merge without tests**: All new logic includes tests

### Frontend ✅

-   **Separate files**: CSS and JS in separate files
-   **Tailwind CSS**: Use Tailwind for styling (no custom CSS unless necessary)
-   **AJAX**: Use native `fetch` API (not jQuery) - jQuery only for DataTables library
-   **UI Components**: Use `x-ui::card` for all page sections and forms
-   **Vite**: Register JS files in `vite.config.js` and run `make assets-build` after changes

### Authorization ✅

-   **Policies**: `TherapistBillPolicy` included for all operations
-   **Authorization**: Use `$this->authorize()` in all controller methods

### Docker & Commands ✅

-   **Docker**: All commands run via Docker (Makefile targets or `docker compose exec`)
-   **Assets Build**: Run `make assets-build` after frontend asset changes
-   **QA**: Run `make qa` before commits (PSR-12, PHPStan, tests)

## Current State

-   ✅ Session logs exist with `therapist_bill_id` (nullable) and `therapist_billable_amount` fields
-   ✅ Session logs can be approved (status: `approved`)
-   ✅ Invoicing system exists as a reference implementation
-   ❌ No therapist billing tables or workflows
-   ❌ No way to generate bills for therapists from approved session logs
-   ❌ No way to send bills to therapists

## Reference Implementation

The invoicing system provides a complete reference:

-   **Tables**: `invoices` (with snapshot fields)
-   **Models**: `Invoice`
-   **DTOs**: `CreateInvoiceDTO`, `SendInvoiceDTO`, `InvoiceFilterDTO`
-   **Services**: `InvoiceService`, `InvoicePdfService`, `CompanyInfoService`
-   **Repositories**: `InvoiceRepositoryInterface`, `EloquentInvoiceRepository`
-   **Controllers**: `Admin\InvoiceController`
-   **Requests**: `CreateInvoiceRequest`, `SendInvoiceRequest`, `InvoiceIndexRequest`
-   **Policies**: `InvoicePolicy`
-   **Mail**: `InvoiceMail`
-   **Views**: Admin views for invoice management
-   **Routes**: Admin routes for invoice CRUD and sending
-   **Tests**: Feature and unit tests

## Database Schema

### 1. `therapist_bills` table

```php
- id (primary key)
- therapist_id (foreign key to users)
- bill_number (unique string)
- billing_period_start (date)
- billing_period_end (date)
- status (enum: draft, sent, paid) - default 'draft'
- subtotal (decimal 10,2) - default 0
- adjustments_total (decimal 10,2) - default 0 (for future adjustments feature)
- total_due (decimal 10,2) - default 0
- due_date (date)
- bill_date (date) - when bill was created
- notes (text, nullable)

// Therapist Snapshot Fields (copied at bill creation)
- therapist_name (string)
- therapist_email (string)
- therapist_phone (string, nullable)
- therapist_address (text, nullable)

// Company Snapshot Fields (copied at bill creation)
- company_name (string)
- company_address (text)
- company_phone (string)
- company_email (string)
- company_tax_id (string, nullable)

// Sending information
- sent_at (datetime, nullable)
- sent_by_id (foreign key to users, nullable)

// Timestamps
- created_at, updated_at, deleted_at (soft deletes)

// Indexes
- therapist_id, status, bill_number, billing_period_start, billing_period_end
```

### 2. Foreign Key Migration

Add foreign key constraint from `session_logs.therapist_bill_id` to `therapist_bills.id`

## Implementation Tasks

### Phase 1: Database & Models

#### 1.1 Create Migration: `create_therapist_bills_table.php`

-   Create the `therapist_bills` table with all fields above
-   Add indexes as specified
-   Add soft deletes

#### 1.2 Create Migration: `add_foreign_key_session_logs_therapist_bill_id.php`

-   Add foreign key constraint from `session_logs.therapist_bill_id` to `therapist_bills.id`
-   Use `nullOnDelete()` since session logs should remain if bill is deleted

#### 1.3 Create Enum: `TherapistBillStatus.php` (NEW - Note: `BillingStatus` enum exists but is for schedule billing status, not therapist bills)

-   Cases: `DRAFT`, `SENT`, `PAID`
-   Methods: `label()`, `values()`, helper methods like `isDraft()`, `isSent()`, `isPaid()`
-   Follow pattern from `InvoiceStatus` enum

#### 1.4 Create Model: `TherapistBill.php`

-   Fillable fields
-   Casts (dates, decimals, enum)
-   Relationships:
    -   `therapist(): BelongsTo`
    -   `sessionLogs(): HasMany`
    -   `sentBy(): BelongsTo`
-   Helper methods: `isDraft()`, `isSent()`, `isPaid()`

#### 1.5 Update Model: `SessionLog.php`

-   Add `therapistBill(): BelongsTo` relationship

### Phase 2: DTOs

#### 2.1 Create DTO: `CreateTherapistBillDTO.php`

```php
- therapistId: int
- sessionLogIds: array<int>
- billNumber?: string (optional, auto-generated if not provided)
- billingPeriodStart: string (date)
- billingPeriodEnd: string (date)
- billDate: string (date)
- dueDate?: string (date, optional, defaults to 30 days)
- notes?: string (optional)
```

#### 2.2 Create DTO: `SendTherapistBillDTO.php`

```php
- email?: string (optional, defaults to therapist email)
- message?: string (optional, custom message)
```

#### 2.3 Create DTO: `TherapistBillFilterDTO.php`

```php
- therapistId?: int
- status?: string
- dateFrom?: string
- dateTo?: string
- search?: string
```

### Phase 3: Repository Layer

#### 3.1 Create Interface: `Domain/Billing/Repositories/TherapistBillRepositoryInterface.php`

Methods:

-   `create(array $data): TherapistBill`
-   `find(int $id): ?TherapistBill`
-   `list(TherapistBillFilterDTO $filters, int $perPage): LengthAwarePaginator`
-   `markAsSent(TherapistBill $bill, int $userId): TherapistBill`
-   `getApprovedSessionLogsForBilling(array $sessionLogIds): Collection<SessionLog>`
-   `getAvailableSessionLogsForBillingCreation(array $filters): Collection<SessionLog>`
-   `getAvailableTherapistIdsForBillingCreation(array $filters): Collection`
-   `linkSessionLogs(TherapistBill $bill, array $sessionLogIds): void`
-   `generateBillNumber(): string`
-   `getBillsByTherapist(int $therapistId, TherapistBillFilterDTO $filters, int $perPage): LengthAwarePaginator`

#### 3.2 Create Implementation: `Infrastructure/Repositories/EloquentTherapistBillRepository.php`

-   Implement all interface methods
-   Follow patterns from `EloquentInvoiceRepository`
-   Use query scopes where appropriate
-   Ensure only approved, unbilled session logs are available

### Phase 4: Service Layer

#### 4.1 Create Service: `Domain/Billing/Services/TherapistBillService.php`

Methods:

-   `generateBill(User $user, CreateTherapistBillDTO $dto): TherapistBill`
    -   Get approved session logs
    -   Validate all belong to same therapist
    -   Calculate totals (sum of `therapist_billable_amount`)
    -   Create therapist snapshot
    -   Create company snapshot (reuse from invoice service)
    -   Create bill record
    -   Link session logs
    -   Return bill with relationships loaded
    -   Keep file under 300 lines (extract helper methods if needed)
-   `calculateTotals(Collection $sessionLogs): array`
    -   Sum `therapist_billable_amount` for subtotal
    -   Add adjustments_total (0 for now, future feature)
    -   Calculate total_due
-   `copyTherapistSnapshot(User $therapist): array`
    -   Copy therapist name, email, phone, address from User/TherapistProfile
-   `sendBill(User $user, TherapistBill $bill, SendTherapistBillDTO $dto): TherapistBill`
    -   Validate bill can be sent (not already sent/paid)
    -   Determine recipient email (DTO email, or therapist email)
    -   Send email with PDF attachment
    -   Mark bill as sent
    -   Return updated bill
-   Use dependency injection for repositories
-   Use Services, not Repositories directly in controllers
-   Keep file under 300 lines (extract methods if needed)

#### 4.2 Create Service: `Domain/Billing/Services/TherapistBillPdfService.php`

-   Generate PDF for therapist bill
-   Follow pattern from `InvoicePdfService`
-   Include bill details, session log lines, totals
-   Use company info from snapshot
-   Keep file under 300 lines (extract methods if needed)

#### 4.3 Reuse/Extend: `Domain/Invoice/Services/CompanyInfoService.php`

-   Reuse existing service for company snapshot data
-   Inject via constructor if needed
-   Or create shared service if not already available

### Phase 5: Form Requests

#### 5.1 Create Request: `Http/Requests/Admin/Billing/CreateTherapistBillRequest.php`

-   Validate session log IDs (required, array, exists in session_logs)
-   Validate therapist ID (required, exists in users, role=therapist)
-   Validate billing period dates
-   Validate bill number (optional, unique if provided)
-   Validate notes (optional, string, max length)

#### 5.2 Create Request: `Http/Requests/Admin/Billing/SendTherapistBillRequest.php`

-   Validate email (optional, email format)
-   Validate message (optional, string, max length)

#### 5.3 Create Request: `Http/Requests/Admin/Billing/TherapistBillIndexRequest.php`

-   Validate filters (therapist_id, status, date_from, date_to, search)
-   Validate per_page (integer, min 1, max 100, default 15)

#### 5.4 Create Request: `Http/Requests/Therapist/Billing/TherapistBillIndexRequest.php`

-   Similar to admin version, but filtered to current therapist only
-   No therapist_id filter (always current user)

### Phase 6: Controllers

#### 6.1 Create Controller: `Http/Controllers/Admin/Billing/TherapistBillController.php`

Actions:

-   `index(TherapistBillIndexRequest $request): View` - List all bills with filters
    -   Use `$this->authorize('viewAny', TherapistBill::class)`
    -   Delegate to Service (not Repository directly)
    -   Keep file under 300 lines (extract methods if needed)
-   `create(Request $request): View` - Show creation form with available session logs
    -   Use `$this->authorize('create', TherapistBill::class)`
-   `store(CreateTherapistBillRequest $request): RedirectResponse` - Create bill
    -   Use `$this->authorize('create', TherapistBill::class)`
    -   Delegate to `TherapistBillService::generateBill()`
-   `show(TherapistBill $bill): View` - Show bill details
    -   Use `$this->authorize('view', $bill)`
-   `send(SendTherapistBillRequest $request, TherapistBill $bill): RedirectResponse` - Send bill
    -   Use `$this->authorize('send', $bill)`
    -   Delegate to `TherapistBillService::sendBill()`
-   `download(TherapistBill $bill): Response` - Download PDF
    -   Use `$this->authorize('view', $bill)`
    -   Delegate to `TherapistBillPdfService::generatePdf()`

#### 6.2 Create Controller: `Http/Controllers/Therapist/Billing/TherapistBillController.php`

Actions:

-   `index(TherapistBillIndexRequest $request): View` - List therapist's own bills
    -   Use `$this->authorize('viewAny', TherapistBill::class)`
    -   Filter to current user's bills only
    -   Keep file under 300 lines
-   `show(TherapistBill $bill): View` - Show therapist's own bill details
    -   Use `$this->authorize('view', $bill)`
-   `download(TherapistBill $bill): Response` - Download PDF
    -   Use `$this->authorize('view', $bill)`
    -   Delegate to `TherapistBillPdfService::generatePdf()`

### Phase 7: Policies

#### 7.1 Create Policy: `Policies/TherapistBillPolicy.php`

Methods:

-   `viewAny(User $user): bool` - Admin only
-   `view(User $user, TherapistBill $bill): bool` - Admin or therapist (own bills only)
-   `create(User $user): bool` - Admin only
-   `send(User $user, TherapistBill $bill): bool` - Admin only, bill must be draft
-   `download(User $user, TherapistBill $bill): bool` - Admin or therapist (own bills only)

### Phase 8: Mail

#### 8.1 Create Mailable: `Mail/TherapistBillMail.php`

-   Follow pattern from `InvoiceMail`
-   Subject: "Bill {bill_number} - {therapist_name}"
-   View: `emails.therapist-bill`
-   Attach PDF using `TherapistBillPdfService`
-   Include custom message if provided

#### 8.2 Create Email View: `resources/views/emails/therapist-bill.blade.php`

-   Follow pattern from `emails/invoice.blade.php`
-   Display bill details, session log summary
-   Include custom message if provided

### Phase 9: Views

#### 9.1 Admin Views: `resources/views/admin/billing/therapist-bills/`

-   `index.blade.php` - List bills with filters (similar to invoices index)
    -   Use `x-ui::card` for page sections
    -   Use `x-admin.layouts.app` layout
    -   Include filters in a card
    -   Include JavaScript via `@vite(['resources/js/pages/admin-therapist-bills-index.js'])` in scripts slot (if needed)
    -   Keep file under 300 lines (extract partials if needed)
-   `create.blade.php` - Create bill form with session log selection (similar to invoices create)
    -   Use `x-ui::card` for form sections
    -   Include JavaScript via `@vite(['resources/js/pages/admin-therapist-bills-create.js'])` in scripts slot
    -   Keep file under 300 lines (extract partials if needed)
-   `show.blade.php` - Bill detail view with session log lines (similar to invoices show)
    -   Use `x-ui::card` for each section (bill details, session logs table, actions)
    -   Include JavaScript via `@vite(['resources/js/pages/admin-therapist-bills-show.js'])` in scripts slot
    -   Keep file under 300 lines (extract partials if needed)
-   Components as needed (e.g., `_filters.blade.php`, `_session-log-row.blade.php`)
    -   Extract to components if main files approach 300 lines

#### 9.2 Therapist Views: `resources/views/therapist/billing/`

-   `index.blade.php` - List therapist's bills
    -   Use `x-ui::card` for page sections
    -   Use `x-therapist.layouts.app` layout
    -   Include JavaScript via `@vite(['resources/js/pages/therapist-bills-index.js'])` in scripts slot (if needed)
    -   Keep file under 300 lines
-   `show.blade.php` - Show bill details (read-only)
    -   Use `x-ui::card` for each section
    -   Keep file under 300 lines (extract partials if needed)

### Phase 10: Routes

#### 10.1 Admin Routes: `routes/admin.php`

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('billing/therapist-bills', Admin\Billing\TherapistBillController::class)
        ->names(['index' => 'billing.therapist-bills.index', ...]);
    Route::post('billing/therapist-bills/{bill}/send', [TherapistBillController::class, 'send'])
        ->name('billing.therapist-bills.send');
    Route::get('billing/therapist-bills/{bill}/download', [TherapistBillController::class, 'download'])
        ->name('billing.therapist-bills.download');
});
```

#### 10.2 Therapist Routes: `routes/therapist.php`

```php
Route::middleware(['auth', 'role:therapist'])->group(function () {
    Route::get('billing', [Therapist\Billing\TherapistBillController::class, 'index'])
        ->name('therapist.billing.index');
    Route::get('billing/{bill}', [Therapist\Billing\TherapistBillController::class, 'show'])
        ->name('therapist.billing.show');
    Route::get('billing/{bill}/download', [Therapist\Billing\TherapistBillController::class, 'download'])
        ->name('therapist.billing.download');
});
```

### Phase 11: Service Provider

#### 11.1 Update: `Providers/AppServiceProvider.php`

-   Bind `TherapistBillRepositoryInterface` to `EloquentTherapistBillRepository`

### Phase 12: Navigation

#### 12.1 Update: `resources/views/layouts/navigation.blade.php`

-   Add "Billing" link in therapist menu section (if not already present)
-   Add "Therapist Billing" link in admin menu section

### Phase 13: JavaScript/Frontend

#### 13.1 Create JS: `resources/js/pages/admin-therapist-bills-create.js`

-   Handle session log selection/checkboxes
-   Form validation
-   Use native `fetch` API for AJAX (not jQuery)
-   Use SweetAlert2 for confirmations (from `common/sweetalert.js`)
-   Similar to `admin-invoices-create.js` pattern
-   Keep file under 300 lines (extract to common module if needed)

#### 13.2 Create JS: `resources/js/pages/admin-therapist-bills-show.js`

-   Handle send bill confirmation (SweetAlert2)
-   Use native `fetch` API for AJAX
-   Similar to `admin-invoices-show.js` pattern
-   Keep file under 300 lines

#### 13.3 Create JS: `resources/js/pages/therapist-bills-index.js` (optional)

-   If therapist index page needs client-side interactivity
-   Use native `fetch` API
-   Keep file under 300 lines

#### 13.4 Update: `vite.config.js`

-   Register new JS entry points in the `input` array:
    -   `resources/js/pages/admin-therapist-bills-create.js`
    -   `resources/js/pages/admin-therapist-bills-show.js`
    -   `resources/js/pages/therapist-bills-index.js` (if created)
-   Run `make assets-build` after registration

### Phase 14: Testing

#### 14.1 Unit Tests: `tests/Unit/Domain/Billing/`

-   `TherapistBillServiceTest.php` - Test bill generation, sending, totals calculation
-   `TherapistBillPdfServiceTest.php` - Test PDF generation
-   `TherapistBillRepositoryTest.php` - Test repository methods

#### 14.2 Unit Tests: `tests/Unit/DTOs/`

-   `CreateTherapistBillDTOTest.php`
-   `SendTherapistBillDTOTest.php`
-   `TherapistBillFilterDTOTest.php`

#### 14.3 Feature Tests: `tests/Feature/Admin/Billing/`

-   `TherapistBillManagementTest.php` - Test admin workflows (create, view, send, download)
-   Test authorization (only admins can create/send)
-   Test session log linking
-   Test bill status transitions

#### 14.4 Feature Tests: `tests/Feature/Therapist/Billing/`

-   `TherapistBillViewTest.php` - Test therapist can view own bills only
-   Test authorization (therapist can only see own bills)

#### 14.5 Browser Tests (Dusk): `tests/Browser/`

-   `TherapistBillCreationTest.php` - Test bill creation workflow
-   `TherapistBillSendingTest.php` - Test sending bills via email

### Phase 15: Factories & Seeders

#### 15.1 Create Factory: `database/factories/TherapistBillFactory.php`

-   Generate test therapist bills
-   Link to session logs

#### 15.2 Update Seeder (optional): Add sample therapist bills if needed

## Key Differences from Invoicing

1. **Grouping**: Bills are grouped by therapist (not school)
2. **Email Recipient**: Send to therapist's email (User.email or TherapistProfile.personal_email)
3. **Amount Source**: Use `therapist_billable_amount` from session logs (not `school_invoice_amount`)
4. **Snapshot**: Copy therapist info (not school info)
5. **Access Control**: Therapists can view their own bills (read-only)

## Implementation Order

1. Database & Models (Phase 1)
2. DTOs (Phase 2)
3. Repository Layer (Phase 3)
4. Service Layer (Phase 4)
5. Form Requests (Phase 5)
6. Policies (Phase 7)
7. Controllers (Phase 6)
8. Mail & Views (Phases 8-9)
9. Routes (Phase 10)
10. Service Provider (Phase 11)
11. Navigation & Frontend (Phases 12-13)
12. Testing (Phase 14)
13. Factories (Phase 15)

## Coding Standards Compliance Checklist

This implementation MUST follow all rules from `.cursor/rules.md`:

### Architecture & Patterns

-   ✅ **Monolith only**: No public API controllers (only Blade pages with Form Requests)
-   ✅ **DTOs**: Always use DTOs for input transport between layers
-   ✅ **Form Requests**: Controllers MUST type-hint Request objects from `app/Http/Requests/**`
-   ✅ **Service Layer**: Controllers delegate to Services; Services use Repositories
-   ✅ **Eloquent**: Prefer Eloquent; raw queries only with justification

### Testing Requirements

-   ✅ **Unit Tests**: For DTOs, Services, Repositories
-   ✅ **Feature Tests**: For routes/commands
-   ✅ **Dusk Tests**: For UI (views, forms, interactions)
-   ✅ **No merge without tests**: All new logic must have tests

### Frontend & Assets

-   ✅ **Separate files**: CSS and JS in separate files
-   ✅ **Tailwind CSS**: Use Tailwind for styles (no custom CSS unless necessary)
-   ✅ **AJAX**: Use native `fetch` API (not jQuery) - jQuery only for DataTables library
-   ✅ **UI Components**: Use `x-ui::card` for page sections and forms
-   ✅ **Vite**: Register JS files in `vite.config.js` and run `make assets-build` after changes

### Code Quality

-   ✅ **Imports**: Always use `use` statements; never fully qualified class names
-   ✅ **PSR-12**: Follow PSR-12 coding standards
-   ✅ **File Size**: Hard cap of 300 lines per file - extract if approaching limit
-   ✅ **Soft Deletes**: Use soft deletes by default on Eloquent models and tables

### Authorization

-   ✅ **Policies**: Always add policies for new models/features
-   ✅ **Authorization**: Use `$this->authorize()` in controllers

### Docker & Commands

-   ✅ **Docker**: Always run commands via Docker (use Makefile targets or `docker compose exec -T app bash -lc 'cd app && <command>'`)
-   ✅ **Assets Build**: Run `make assets-build` after frontend asset changes

## Open Questions / Future Enhancements

1. **Adjustments**: The PRD mentions `therapist_adjustments` table for bonuses/deductions. This can be added later.
2. **Payment Recording**: The PRD mentions payment tracking. This can be added later.
3. **Dispute Handling**: The PRD mentions therapist disputes. This can be added later.
4. **Export**: The PRD mentions export to payroll. This can be added later.
5. **Billing Period**: Should bills be generated weekly, biweekly, or monthly? Start with flexible period selection.
6. **Email Preference**: Use User.email or TherapistProfile.personal_email? Prefer personal_email, fallback to User.email.

## Implementation Notes

-   Follow all existing coding standards and patterns from the invoice implementation
-   All files must stay under 300 lines - extract to components/services if needed
-   Use `x-ui::card` component for all page sections
-   Use native `fetch` API for AJAX (jQuery only for DataTables)
-   Register all JS files in `vite.config.js` and run `make assets-build`
-   Use dependency injection throughout (Services in Controllers, Repositories in Services)
-   Always use `$this->authorize()` in controllers
-   Write tests before implementation (TDD approach recommended)
-   Use SweetAlert2 from `common/sweetalert.js` for confirmations
-   Use Tailwind CSS classes for styling
-   All commands must run via Docker
-   Ensure soft deletes on all models
-   Use `use` statements for all imports
