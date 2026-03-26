NOVA · Payments PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Payments module handles recording and managing payments against invoices (AR) and therapist bills (AP). It also integrates with Stripe for online invoice payments via payment links.

2. FUNCTIONAL SCOPE

   2.1 Invoice Payments (AR)
   - Record payments against specific invoices
   - Payment allocation across multiple invoices
   - Global payments list with DataTable
   - Per-invoice payment recording from invoice detail page
   - Delete payment (reverses allocation)
   Payment methods: check, bank_transfer, ach, wire, direct_deposit, cash, credit_card, other (PaymentMethod enum)

   2.2 Therapist Bill Payments (AP)
   - Record payments against specific therapist bills
   - Payment allocation across multiple bills
   - Global payments list with DataTable
   - Per-bill payment recording from bill detail page
   - Delete payment (reverses allocation)

   2.3 Stripe Payment Gateway
   - Payment links generated per invoice via `Invoice::ensurePaymentToken()` / `getPaymentUrl()`
   - Public payment page: GET /payment/{token}
   - Checkout flow: POST /payment/{token}/checkout → Stripe Checkout Session
   - Success callback: GET /payment/success
   - Cancel callback: GET /payment/cancel/{token}
   - Transaction tracking: `PaymentGatewayTransaction` model
   - Webhook logging: `PaymentGatewayLog` model (outgoing requests + incoming webhooks)
   - Status: pending, completed, expired, cancelled, failed (PaymentGatewayTransactionStatus enum)
   - Gateway: stripe (PaymentGateway enum)

3. DATA MODEL
   Table: invoice_payments — `id`, `payment_date`, `amount`, `payment_method`, `reference_number`, `notes`, timestamps, `deleted_at`.
   Table: invoice_payment_allocations — `id`, `invoice_id`, `invoice_payment_id`, `amount`, timestamps.
   Table: therapist_bill_payments — `id`, `payment_date`, `amount`, `payment_method`, `reference_number`, `notes`, timestamps, `deleted_at`.
   Table: therapist_bill_payment_allocations — `id`, `therapist_bill_id`, `therapist_bill_payment_id`, `amount`, timestamps.
   Table: payment_gateway_transactions — `id`, `invoice_id`, `gateway` (PaymentGateway), `session_id`, `status` (PaymentGatewayTransactionStatus), `amount`, `currency`, timestamps.
   Table: payment_gateway_logs — `id`, `direction` (PaymentGatewayLogDirection: outgoing/incoming), `payload`, `response`, timestamps.

4. ROUTES
   Invoice Payments:
   - GET /admin/payments/invoices — global list
   - POST /admin/payments/invoices/data — DataTable endpoint
   - GET /admin/payments/invoices/create — create form
   - POST /admin/payments/invoices — store
   - DELETE /admin/payments/invoices/{payment} — delete
   - POST /admin/invoices/{invoice}/payments — record payment on invoice
   - DELETE /admin/invoices/{invoice}/payments/{payment} — delete allocation

   Therapist Bill Payments:
   - GET /admin/payments/therapist-bills — global list
   - POST /admin/payments/therapist-bills/data — DataTable endpoint
   - GET /admin/payments/therapist-bills/create — create form
   - POST /admin/payments/therapist-bills — store
   - DELETE /admin/payments/therapist-bills/{payment} — delete
   - POST /admin/billing/therapist-bills/{bill}/payments — record payment on bill
   - DELETE /admin/billing/therapist-bills/{bill}/payments/{payment} — delete allocation

   Stripe Payments (Public):
   - GET /payment/{token} — payment page
   - POST /payment/{token}/checkout — initiate Stripe checkout
   - GET /payment/success — success callback
   - GET /payment/cancel/{token} — cancel callback

5. TECHNICAL IMPLEMENTATION
   Controllers: `InvoicePaymentController`, `InvoicePaymentsListController`, `TherapistBillPaymentController`, `TherapistBillPaymentsListController`, `PaymentController` (public)
   Policies: `InvoicePaymentPolicy`, `TherapistBillPaymentPolicy`
   Models: `InvoicePayment`, `InvoicePaymentAllocation`, `TherapistBillPayment`, `TherapistBillPaymentAllocation`, `PaymentGatewayTransaction`, `PaymentGatewayLog`
