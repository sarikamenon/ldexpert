NOVA · Pay Stub Report PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Pay Stub Report generates year-filtered summaries of therapist payments. Admins can view payment totals per therapist and download individual pay stub PDFs.

2. FUNCTIONAL SCOPE

   2.1 Report View
   Route: GET /admin/finance/pay-stub-report
   - Year filter (dropdown)
   - Server-side DataTable showing therapist name, total paid, number of bills
   - DataTable endpoint: POST /admin/finance/pay-stub-report/data

   2.2 PDF Download
   Route: GET /admin/finance/pay-stub-report/download
   - Per-therapist per-year pay stub PDF
   - Request: `PayStubDownloadRequest` (validates therapist_id, year)
   - Generated from `admin/finance/pay-stub-report/pdf.blade.php`

3. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\Finance\PayStubReportController`
   Methods: `index()`, `data()`, `download()`

4. NAVIGATION
   Appears under "Finance" top-level admin menu as "Pay Stub Report" entry.
