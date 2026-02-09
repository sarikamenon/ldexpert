# Therapist Billing Implementation - Quick Summary

## Goal

Create billing system for approved session logs and send bills to therapists via email, mirroring the invoice system for schools.

## Core Components Needed

### Database

-   **New Table**: `therapist_bills` (similar to `invoices`)
-   **Foreign Key**: Link `session_logs.therapist_bill_id` → `therapist_bills.id`
-   **New Enum**: `BillingStatus` (DRAFT, SENT, PAID)

### Models & Relationships

-   `TherapistBill` model
-   `SessionLog::therapistBill()` relationship

### Services

-   `TherapistBillService` - Generate bills, send bills, calculate totals
-   `TherapistBillPdfService` - Generate PDF statements
-   Reuse `CompanyInfoService` for company snapshot

### Controllers

-   `Admin\Billing\TherapistBillController` - Admin CRUD and sending
-   `Therapist\Billing\TherapistBillController` - Therapist view-only access

### Key Features

1. **Bill Generation**: From approved session logs, grouped by therapist
2. **Totals**: Sum of `therapist_billable_amount` from session logs
3. **Email Sending**: Send PDF bill to therapist's email
4. **Access Control**: Admins create/send, therapists view own bills only

### Workflow

1. Admin selects approved session logs for a therapist
2. System creates bill with totals
3. Admin sends bill via email (with PDF attachment)
4. Therapist receives email and can view/download from portal

### Reference Implementation

-   Follow patterns from `Invoice` system exactly
-   Same structure: DTOs, Repositories, Services, Controllers, Policies, Mail, Views
-   Key difference: Group by therapist (not school), use `therapist_billable_amount`

## Implementation Phases

1. Database & Models
2. DTOs & Repository Layer
3. Service Layer
4. Controllers & Policies
5. Mail & Views
6. Routes & Navigation
7. Testing

See `THERAPIST_BILLING_IMPLEMENTATION_PLAN.md` for detailed breakdown.
