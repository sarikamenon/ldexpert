# RSM / CAVA Sync PRD

## Purpose
Automate data synchronization between NOVA and upstream school systems (RSM, CAVA, others) to eliminate duplicate data entry and ensure consistency across students, sessions, rates, and financials.

## Personas
- **Integration Admin** — configures connectors, monitors jobs, resolves failures.
- **Program Ops** — relies on up-to-date student/session data without manual re-entry.
- **Finance** — depends on accurate session imports for invoicing/billing.

## Current Implementation
- No integration layer exists. All imports are manual spreadsheets.

## Planned Scope
- Build a pluggable sync framework capable of:
  - Importing students, SSAs, sessions, and rates from RSM/CAVA via API or SFTP.
  - Exporting therapist assignments, schedules, and invoice status back to partners (if required).
  - Handling retries, conflict detection, and audit logs.
- Provide admin UI to run one-off syncs, view job history, and map external IDs.

## Domain Model
### Tables
| Table | Fields |
| --- | --- |
| `integration_sources` | `id`, `name` (RSM, CAVA), `type` (api, sftp), `base_url`, `auth_config` (encrypted json), `is_active`, timestamps.
| `integration_jobs` | `id`, `source_id`, `job_type` (students, sessions, rates), `status` (queued, running, succeeded, failed, partial), `started_at`, `finished_at`, `stats` (json), `error_message`.
| `integration_job_items` | `job_id`, `external_id`, `entity_type`, `status`, `payload_snapshot`, `error`.
| `external_id_mappings` | `entity_type`, `nova_id`, `external_id`, `source_id`, unique per pair.
| `staging_sessions`, `staging_students` | hold raw payloads for validation before merging into core tables.

### Rules
- All imports run through staging + validation before touching prod tables.
- Conflict resolution strategy configurable per entity (upstream authoritative vs. NOVA authoritative vs. manual review).
- External IDs stored on core tables (`schools.external_ref`, `service_support_agreements.external_ref`, etc.).

## API & Routes
### Admin Web (`/admin/integrations`)
- `GET /admin/integrations/sources` — list connectors with status and token expiry.
- `GET /admin/integrations/jobs` — history with filters.
- `POST /admin/integrations/jobs` — trigger manual job (entity + date range).
- `GET /admin/integrations/jobs/{job}` — detail + item-level errors, reprocess buttons.
- `POST /admin/integrations/jobs/{job}/retry` and `POST /admin/integrations/jobs/{job}/items/{item}/resolve`.

### Webhooks / APIs
- Expose `/api/v1/integrations/{source}/callbacks` to accept push updates if RSM supports webhooks.
- Provide `/api/v1/export/assignments` for partners that pull therapist assignment data.

## Workflows
1. **Scheduled Import**
   1. Nightly scheduler creates `integration_job` per entity.
   2. Worker fetches data (paging or file download), stores payloads in staging tables.
   3. Validation rules run (school exists, SSA references valid services). Failures logged per item.
   4. Successful rows upsert into core tables (students, sessions, rates) while preserving manual overrides.
2. **Conflict Resolution**
   1. If incoming record conflicts with local changes (e.g., guardian contact edited in NOVA), item flagged `needs-review`.
   2. Admin UI displays diff; user chooses "upstream wins" or "NOVA wins". Decision recorded to audit log.
3. **Backfill / Historical Import**
   1. Admin selects date range; system paginates through upstream API and applies same staging flow.
   2. Rate limiting/resume tokens handled automatically.

## Monitoring & Alerts
- Real-time status board with counts of failed/pending items.
- PagerDuty/Slack alerts when job failure rate exceeds threshold.
- Metrics stored in `integration_jobs.stats` (records processed, failures, latency).

## Dependencies
- Requires all core modules to expose stable APIs (students, SSAs, sessions, rates).
- Authentication secrets stored via Laravel config + encrypted storage (e.g., AWS Secrets Manager) rather than `.env` when possible.

## Risks & Open Questions
- Need clarity on exact data formats from RSM vs. CAVA (REST? CSV?).
- Determine whether NOVA or upstream is source of truth for session approval (affects invoice/billing pipelines).
- Consider GDPR/FERPA obligations when storing raw payload snapshots.
