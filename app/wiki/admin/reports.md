# Reports Module PRD

## Purpose

The Reports module provides administrators with comprehensive analytics and insights into Service Support Agreement (SSA) performance, therapist caseloads, utilization metrics, and compliance tracking. Reports enable data-driven decision making, identify trends, support compliance requirements, and help manage operations effectively.

## Personas

- **Program Administrator** — reviews utilization, compliance, and caseload distribution to optimize operations.
- **Finance Team** — uses utilization reports to reconcile billing and identify revenue opportunities.
- **Clinical Supervisor** — monitors therapist caseloads and assignment patterns for workload management.
- **Operations Manager** — tracks SSA expirations and pipeline to ensure continuity of care.

## Current Implementation

### SSA Utilization & Compliance Report ✅

**Purpose:** Track service delivery against authorized units, identify utilization variances, and ensure compliance with SSA terms.

**Features:**
- **Utilization Metrics:** Compare authorized vs. delivered minutes/hours per SSA
- **Compliance Tracking:** Identify SSAs with under/over-utilization
- **Variance Analysis:** Calculate percentage of authorized units utilized
- **Service Breakdown:** View utilization by service type
- **Time Period Filtering:** Filter by date range, school, therapist, service
- **Export Functionality:** Export report data to CSV for further analysis

**Data Included:**
- SSA details (student, school, service, therapist, dates)
- Authorized frequency and units
- Delivered sessions count and minutes/hours
- Utilization percentage
- Variance (authorized vs. delivered)
- Status indicators for compliance thresholds

**Routes:**
- `GET /admin/reports/ssa/utilization` - View utilization report
- `GET /admin/reports/ssa/utilization/export` - Export utilization report to CSV

**Controller:** `App\Http\Controllers\Admin\Reports\SSAUtilizationReportController`

**Service:** `App\Domain\SSA\Services\SSAUtilizationReportService`

**Repository:** `App\Infrastructure\Repositories\EloquentSSARepository::getUtilizationReport()`

**View:** `resources/views/admin/reports/ssa/utilization.blade.php`

### SSA Caseload & Assignment Report ✅

**Purpose:** Monitor therapist caseload distribution, student assignments, and workload balance across the organization.

**Features:**
- **Caseload Overview:** View all therapist-student assignments via SSAs
- **Therapist Workload:** See number of active students per therapist
- **Service Distribution:** Track service types assigned to each therapist
- **Assignment Patterns:** Identify assignment trends and patterns
- **Filtering:** Filter by therapist, school, service, date range
- **Export Functionality:** Export caseload data to CSV

**Data Included:**
- Therapist information (name, email, active status)
- Student assignments (student name, school, service)
- SSA details (dates, frequency, status)
- Assignment counts and statistics
- Service type breakdown per therapist

**Routes:**
- `GET /admin/reports/ssa/caseload` - View caseload report
- `GET /admin/reports/ssa/caseload/export` - Export caseload report to CSV

**Controller:** `App\Http\Controllers\Admin\Reports\SSACaseloadReportController`

**Service:** `App\Domain\SSA\Services\SSACaseloadReportService`

**Repository:** `App\Infrastructure\Repositories\EloquentSSARepository::getCaseloadReport()`

**View:** `resources/views/admin/reports/ssa/caseload.blade.php`

### SSA Expirations & Pipeline Report ✅

**Purpose:** Track upcoming SSA expirations, identify renewal opportunities, and manage the pipeline of expiring agreements.

**Features:**
- **Expiration Tracking:** Categorize SSAs by expiration status:
  - **Upcoming:** SSAs expiring within configurable window (default: 90 days)
  - **Expired:** SSAs that have expired
  - **Pending:** SSAs pending activation or approval
  - **No Current:** Students without active SSAs
- **Pipeline Management:** View all SSAs grouped by expiration status
- **Renewal Planning:** Identify SSAs needing renewal attention
- **Filtering:** Filter by school, therapist, service, expiration date range
- **Export Functionality:** Export expiration data to CSV

**Data Included:**
- SSA details (student, school, service, therapist)
- Start and end dates
- Days until expiration (for upcoming)
- Days since expiration (for expired)
- Status indicators
- Renewal recommendations

**Routes:**
- `GET /admin/reports/ssa/expirations` - View expirations report
- `GET /admin/reports/ssa/expirations/export` - Export expirations report to CSV

**Controller:** `App\Http\Controllers\Admin\Reports\SSAExpirationReportController`

**Service:** `App\Domain\SSA\Services\SSAExpirationReportService`

**Repository:** `App\Infrastructure\Repositories\EloquentSSARepository::getExpirationReport()`

**View:** `resources/views/admin/reports/ssa/expirations.blade.php`

**Configuration:**
- Default expiration window: 90 days (configurable via filter)
- Buckets: `upcoming`, `expired`, `pending`, `no_current`

## Domain Model

### Report Data Sources

All reports query from the following core tables:

- `service_support_agreements` - Primary SSA data
- `users` - Student and therapist information
- `schools` - School details
- `services` - Service catalog
- `session_logs` - Session delivery data (for utilization)
- `schedules` - Scheduled sessions

### DTOs (Data Transfer Objects)

- `App\DTOs\SSAReport\UtilizationReportFilterDTO` - Filters for utilization reports
- `App\DTOs\SSAReport\CaseloadReportFilterDTO` - Filters for caseload reports
- `App\DTOs\SSAReport\ExpirationReportFilterDTO` - Filters for expiration reports

### Request Validation

- `App\Http\Requests\Admin\Reports\SSA\UtilizationReportRequest` - Validation for utilization report filters
- `App\Http\Requests\Admin\Reports\SSA\CaseloadReportRequest` - Validation for caseload report filters
- `App\Http\Requests\Admin\Reports\SSA\ExpirationReportRequest` - Validation for expiration report filters

## Routes & Controllers

### Admin Routes

```
GET  /admin/reports/ssa/utilization           - View utilization report
GET  /admin/reports/ssa/utilization/export    - Export utilization CSV
GET  /admin/reports/ssa/caseload              - View caseload report
GET  /admin/reports/ssa/caseload/export       - Export caseload CSV
GET  /admin/reports/ssa/expirations           - View expirations report
GET  /admin/reports/ssa/expirations/export    - Export expirations CSV
```

All routes protected by `auth` + `role:admin` middleware and require `viewAny` permission on `ServiceSupportAgreement` model.

## Workflows

### 1. Utilization Report Workflow

1. Admin navigates to Reports → SSA Reports → Utilization & Compliance
2. System displays filter form (date range, school, therapist, service)
3. Admin selects filters and clicks "Generate Report"
4. System queries session logs and SSAs based on filters
5. System calculates utilization metrics (authorized vs. delivered)
6. System displays report with summary statistics and detailed table
7. Admin can export report to CSV for further analysis

### 2. Caseload Report Workflow

1. Admin navigates to Reports → SSA Reports → Caseload & Assignment
2. System displays filter form (therapist, school, service, date range)
3. Admin selects filters and clicks "Generate Report"
4. System queries SSAs and assignments based on filters
5. System aggregates caseload data by therapist
6. System displays report with therapist workload breakdown
7. Admin can export report to CSV

### 3. Expirations Report Workflow

1. Admin navigates to Reports → SSA Reports → Expirations & Pipeline
2. System displays filter form (school, therapist, service, expiration window)
3. Admin selects filters and clicks "Generate Report"
4. System categorizes SSAs into buckets (upcoming, expired, pending, no current)
5. System displays report with categorized sections and summary
6. Admin can export specific bucket or all data to CSV

## Filtering & Query Capabilities

### Common Filters

All reports support filtering by:
- **Date Range:** Start and end dates for report period
- **School:** Filter by specific school(s)
- **Therapist:** Filter by specific therapist(s)
- **Service:** Filter by service type(s)
- **Status:** Filter by SSA status (active, expired, pending)

### Advanced Filters (Future)

- **Student:** Filter by specific student(s)
- **Billing Status:** Filter by billing status
- **Utilization Threshold:** Filter by utilization percentage ranges
- **Compliance Status:** Filter by compliance indicators

## Export Functionality

### CSV Export

- **Format:** Standard CSV format with headers
- **Columns:** All visible report columns included
- **File Naming:** `report-name-YYYY-MM-DD-HHMMSS.csv`
- **Character Encoding:** UTF-8 with BOM for Excel compatibility
- **Streaming:** Large exports streamed to avoid memory issues

### Export Limitations

- **Row Limits:** No hard limit, but large exports may take time
- **Data Scope:** Export reflects current filter selections
- **Real-time:** Exports are generated on-demand, not scheduled

## Security & Permissions

- **Authorization:** All reports require `role:admin` and `viewAny` permission on `ServiceSupportAgreement`
- **Data Access:** Reports respect soft deletes (exclude deleted SSAs/students)
- **PII Protection:** Student and therapist data included only as needed for reports
- **Audit Trail:** Report access can be logged (future enhancement)

## Performance Considerations

- **Query Optimization:** Reports use optimized queries with proper indexing
- **Caching:** Summary statistics can be cached (future enhancement)
- **Pagination:** Large reports paginated in views (export includes all data)
- **Lazy Loading:** Relationships loaded efficiently to avoid N+1 queries

## Dependencies

- **SSA Module:** Primary data source for all reports
- **Session Logs Module:** Provides delivery data for utilization calculations
- **User Module:** Provides student and therapist information
- **School Module:** Provides school details
- **Service Module:** Provides service catalog information

## Metrics & KPIs

### Utilization Report Metrics

- **Overall Utilization Rate:** Average utilization across all SSAs
- **Under-Utilization Count:** SSAs below utilization threshold
- **Over-Utilization Count:** SSAs above utilization threshold
- **Compliance Rate:** Percentage of SSAs within acceptable utilization range

### Caseload Report Metrics

- **Average Caseload:** Average students per therapist
- **Caseload Distribution:** Min, max, median students per therapist
- **Workload Balance:** Standard deviation of caseload distribution
- **Service Diversity:** Average services per therapist

### Expiration Report Metrics

- **Expiration Rate:** Number of SSAs expiring per month
- **Renewal Rate:** Percentage of SSAs renewed before expiration
- **Pipeline Health:** Ratio of upcoming to expired SSAs
- **Gap Analysis:** Students without active SSAs

## Planned Scope (Future Enhancements)

### Additional Reports

- **Billing & Revenue Reports:** Track invoice amounts, outstanding balances, payment trends
- **Therapist Performance Reports:** Track therapist utilization, session completion rates, quality metrics
- **Student Progress Reports:** Track student outcomes, goal achievement, session attendance
- **Financial Reports:** Revenue by school, service, therapist; cost analysis
- **Compliance Reports:** Regulatory compliance tracking, documentation completion rates

### Enhanced Features

- **Scheduled Reports:** Email reports on a schedule (daily, weekly, monthly)
- **Custom Reports:** Admin-defined custom report builder
- **Dashboard Widgets:** Report summaries on admin dashboard
- **Report Comparison:** Compare reports across time periods
- **Visualizations:** Charts and graphs for better data visualization
- **Drill-Down:** Click-through from summary to detail views
- **Report Templates:** Save and reuse common report configurations

### Analytics Enhancements

- **Trend Analysis:** Identify trends over time
- **Predictive Analytics:** Forecast utilization, expirations, revenue
- **Anomaly Detection:** Identify unusual patterns or outliers
- **Benchmarking:** Compare metrics against industry standards

## Risks & Open Questions

- **Data Accuracy:** Ensure session logs accurately reflect delivered services
- **Performance:** Large date ranges may impact query performance
- **Real-time vs. Batch:** Determine if reports need real-time data or can use cached/summarized data
- **Data Retention:** How long to retain historical report data
- **Export Limits:** Need to define maximum export size limits
- **Report Scheduling:** Determine best approach for scheduled report delivery

## Version 2 Backlog

- **Interactive Dashboards:** Interactive charts and filters on report pages
- **Report Builder:** Drag-and-drop custom report builder
- **API Access:** REST API for programmatic report access
- **Report Templates:** Pre-built report templates for common use cases
- **Alert System:** Automated alerts for threshold violations (e.g., low utilization, upcoming expirations)
- **Comparative Reports:** Side-by-side comparison of multiple reports
- **Export Formats:** Support for PDF, Excel, JSON export formats
- **Report Sharing:** Share reports with specific users or teams
- **Historical Snapshots:** Save report snapshots for historical comparison
