# Manage Services PRD

## Purpose
Define the catalog of services NOVA offers (SLP, OT, PT, Progress Reports, IEP meetings, evaluations, etc.) with standardized metadata that drives SSA authorizations, scheduling, invoicing, and therapist payouts.

## Personas
- **Program Admin** — manages service definitions, categories, GL codes, default rates.
- **Finance** — references billing metadata (unit type, rate defaults, revenue recognition).
- **Therapists** — need to know available modalities and documentation requirements per service.

## Current Implementation
- No service catalog exists; service names are free text in SSA discussions and invoices. Therapists currently add sessions without structured service metadata.

## Planned Scope
- Create normalized `services` table with taxonomy grouping (clinical, admin, meeting) and attributes (unit type, documentation template, defaults for client and provider rates).
- Support service-specific requirements: minimum session length, default goal template, whether progress notes are mandatory, whether service supports telehealth.
- Allow custom fields per service (e.g., `requires_supervisor`, `report_due_days` for Progress Reports).
- Provide API for referencing service metadata externally and UI for editing with version history.

## Domain Model
### Tables
| Table | Fields |
| --- | --- |
| `services` | `id`, `code` (unique), `name`, `description`, `category`, `delivery_modes` (json), `unit_type` (minute, session, report), `default_client_rate`, `default_provider_rate`, `min_duration_minutes`, `max_duration_minutes`, `documentation_template`, `is_billable`, `is_active`, `created_at`, `updated_at`, `deleted_at`.
| `service_tags` | optional lookup to tag services (e.g., Medicaid-only).
| `service_requirements` | `service_id`, `requirement_type` (note, attachment), `value` (json) for dynamic rule evaluation.

### Rules
- `code` must be immutable once used in SSA/invoice.
- Only active services appear in SSA, scheduling, therapist selection.
- Rate defaults cascade to `school_service_rates` and `therapist_service_rates` when new rows created.

## API & Routes
### Admin Web
- `GET /admin/services` (list with filters for category, active state).
- `GET /admin/services/create` & `POST /admin/services`.
- `GET /admin/services/{service}` with tabs: overview, documentation requirements, rate defaults, usage metrics.
- `PATCH /admin/services/{service}` — updates metadata (audit old values).
- `PATCH /admin/services/{service}/status` — activate/deactivate.

### API v1
- `GET /api/v1/services` (public read) for other systems to map codes.
- `GET /api/v1/services/{code}` returns full metadata, including documentation template.

## Workflows
1. **Create Service**
   1. Admin defines code + category, chooses unit type and default rates.
   2. System generates documentation template from base (select from library) and sets telehealth compatibility.
   3. Upon activation, notify SSA creators and therapists about the new service.
2. **Update Requirements**
   1. Admin edits note template or requirement to upload progress report.
   2. System version-controls templates; existing sessions keep previous template while future ones use new version.
3. **Deactivate Service**
   1. Admin sets service to inactive; system prevents new SSA lines but allows historical data for reporting.

## Integrations & Dependencies
- SSA module references `services.id` for each line.
- Invoicing/Billing compute rates based on service defaults + overrides.
- Therapist management uses service definitions to determine competencies and pay rates.
- RSM/CAVA sync should map external service codes to NOVA `services.code`.

## Metrics
- Service utilization by school/therapist.
- Average rate per service vs. default.
- Compliance: percentage of sessions with required documentation attached.

## Risks & Open Questions
- Need canonical list of service categories and GL mappings.
- Determine how to model composite services (e.g., session + progress report) — multi-line SSA or nested service packages?
- Confirm whether service documentation templates need localization or multi-language support.
