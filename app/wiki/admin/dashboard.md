NOVA · Admin Dashboard PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Admin Dashboard is the landing page for system administrators after login. It provides an at-a-glance view of platform health, key metrics, critical alerts, operational status, and quick actions to common admin tasks.

2. OBJECTIVES
   - Surface key business metrics (schools, therapists, students, SSAs) without manual querying.
   - Highlight critical alerts requiring immediate attention (expiring SSAs, pending approvals, overdue items).
   - Provide quick navigation to common admin workflows.
   - Display chart-based visualizations for trend analysis.

3. PERSONA & ROLE
   Persona: System Admin / Operations Manager | Role: Role::ADMIN | Goals: Monitor platform health, identify urgent items, quickly navigate to common tasks.

4. FUNCTIONAL SCOPE
   4.1 Key Metrics
   Dashboard cards showing aggregate counts for:
   - Total Schools (active)
   - Total Therapists (active)
   - Total Students (active)
   - Total SSAs (active/expiring)
   Each metric links to its respective list page.

   4.2 Critical Alerts
   Alert cards highlighting items requiring action:
   - SSAs expiring within 30 days
   - Session logs pending approval
   - Unbilled approved session logs
   - Other configurable threshold alerts
   Alerts are dismissible per session but reappear on next login.

   4.3 Chart Data
   Visual charts (Chart.js via CDN) for:
   - Session logs by status over time
   - SSA utilization rates
   - Revenue/billing trends
   Charts are read-only and update on page load.

   4.4 Upcoming Events
   List of upcoming scheduled sessions, expiring SSAs, and contract renewals within a configurable time window.

   4.5 Operational Metrics
   System health indicators:
   - Queue status (pending/failed jobs)
   - Recent import statuses
   - Email delivery health

   4.6 Quick Actions
   Shortcut buttons to frequently used admin tasks:
   - Create Student
   - Create SSA
   - View Session Logs (pending approval)
   - Generate Invoice
   - Generate Therapist Bill

5. USER EXPERIENCE GUIDELINES
   - Dashboard loads all data server-side via DashboardService — no AJAX lazy loading.
   - Metric cards use `x-dashboard:metric` Blade component.
   - Charts render via Chart.js CDN with responsive sizing.
   - Alerts are visually distinct (warning/danger colors from design system).
   - Layout is responsive: cards stack on mobile, grid on desktop.

6. DATA MODEL
   No dedicated tables. Dashboard aggregates data from:
   - `users` (counts by role/status)
   - `schools` (active count)
   - `service_support_agreements` (status, expiration)
   - `session_logs` (status counts, billing amounts)
   - `invoices` / `therapist_bills` (pending amounts)

7. ROUTES (INTERNAL WEB APP)
   - GET /admin/dashboard — main dashboard view.

8. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\DashboardController`
   Service: `App\Services\DashboardService`
   View: `resources/views/admin/dashboard.blade.php`

   DashboardService provides:
   - `getKeyMetrics()` — aggregate counts
   - `getCriticalAlerts()` — threshold-based alerts
   - `getChartData()` — formatted chart datasets
   - `getUpcomingEvents()` — time-windowed events
   - `getOperationalMetrics()` — system health
   - `getQuickActions()` — configured action links

9. OPEN QUESTIONS & RISKS
   - Dashboard query performance may need caching as data grows.
   - Chart data scope (30 days? 90 days?) should be configurable.
   - Consider adding a finance dashboard summary widget (currently separate at /admin/finance/dashboard).
