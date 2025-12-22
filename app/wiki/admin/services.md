# Manage Services PRD

## Purpose

Define the catalog of services NOVA offers (SLP, OT, PT, Progress Reports, IEP meetings, evaluations, etc.) with standardized metadata that drives SSA authorizations, scheduling, invoicing, and therapist payouts.

## Personas

-   **Program Admin** — manages service definitions, categories, GL codes, default rates.
-   **Finance** — references billing metadata (unit type, rate defaults, revenue recognition).
-   **Therapists** — need to know available modalities and documentation requirements per service.

## Current Implementation

-   Normalized `services` table with soft deletes.
-   Fields: `name` (unique), `description`, `is_direct_service`, `is_group_service`, `is_frequency_service`, `delivery_mode` (`virtual`/`in_person`/`hybrid`, default `virtual`), `is_billable`, `min_duration_minutes`, `max_duration_minutes`, `status` (enum active/inactive).
-   Delivery mode helpers: `Service::DELIVERY_MODE_OPTIONS`, default via `Service::defaultDeliveryMode()`.
-   Only active services surface in SSA creation, scheduling, and rate pickers.

## Backlog (Not Implemented)

-   Service codes/GL mappings and documentation templates.
-   Default client/provider rates on the service.
-   Public API endpoints; current usage is internal admin UI and downstream modules.

## Domain Model

### Tables (Implemented)

| Table      | Fields                                                                                                                                                                                                           |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `services` | `id`, `name`, `description`, `is_direct_service`, `is_group_service`, `is_frequency_service`, `delivery_mode`, `is_billable`, `min_duration_minutes`, `max_duration_minutes`, `status`, timestamps, `deleted_at` |

### Rules (Implemented)

-   Only active services appear in SSA, scheduling, therapist/service-rate pickers.
-   Delivery mode constrained to predefined options; default is `virtual`.

## API & Routes

### Admin Web (Implemented)

-   `GET /admin/services` — list with filters/status toggle.
-   `GET /admin/services/create` & `POST /admin/services` — creation flow.
-   `GET /admin/services/{service}` — view/edit (no destroy).
-   `PATCH /admin/services/{service}` — update metadata.
-   `PATCH /admin/services/{service}/status` — activate/deactivate.

## Workflows (Implemented)

1. Create/edit service metadata (name, description, delivery mode, billable flag, duration bounds, type booleans) and activate to expose in downstream pickers.
2. Toggle status to hide from new SSAs/schedules while preserving history.

## Integrations & Dependencies

-   SSA module uses `service_id` for primary and additional services.
-   Scheduling stores `service_id` on schedules for reminders and billing state.
-   School/Therapist contract rate tables reference `service_id` for overrides.

## Metrics (Current)

-   Active vs. inactive counts; downstream reporting can show SSA and schedule usage.

## Risks & Open Questions

-   Need service codes/GL mappings and rate strategy.
-   Documentation templates/compliance requirements are not modeled yet.
-   No public API surface; consider versioned API when external sync is required.
