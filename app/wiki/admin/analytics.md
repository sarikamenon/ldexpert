NOVA · Analytics PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Analytics module provides data visualization dashboards for operational insights across the platform. It offers overview, school-focused, and therapist-focused analytics with configurable date ranges.

2. FUNCTIONAL SCOPE

   2.1 Analytics Overview
   Route: GET /admin/analytics
   Dashboard with aggregate metrics across all entities for the selected date range.

   2.2 School Analytics
   Route: GET /admin/analytics/schools
   School-focused metrics: revenue, session counts, utilization rates per school.

   2.3 Therapist Analytics
   Route: GET /admin/analytics/therapists
   Therapist-focused metrics: caseload, session counts, billing amounts per therapist.

   2.4 Date Range Filters
   All views support configurable date ranges via `AnalyticsFilterRequest`:
   - last_7_days
   - last_30_days (default)
   - last_90_days
   - this_month
   - last_month
   - this_year
   - custom (with start_date / end_date)

3. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\AnalyticsController`
   Methods: `index()`, `schools()`, `therapists()`
   Form Request: `AnalyticsFilterRequest`

4. ROUTES
   - GET /admin/analytics — overview dashboard
   - GET /admin/analytics/schools — school analytics
   - GET /admin/analytics/therapists — therapist analytics

5. NAVIGATION
   Appears under "Reports" top-level admin menu as "Analytics" entry.
