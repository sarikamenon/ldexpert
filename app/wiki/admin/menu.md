# Admin Menu Specification

## Purpose

Define the top-level navigation for NOVA administrators so that Schools, Therapists, Students, Service Support Agreements, and Services each have a clearly scoped entry point. The menu references existing PRDs inside `app/wiki/admin` and guides engineering on how to wire routes, permissions, and UI states.

## Menu Items

| Menu                       | Primary Responsibilities                                                                                                                                    | Key Routes                                                                                                                                          | Dependencies                                                                   |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| Schools                    | Onboard/edit schools, manage contacts, geography, timezones, and service-rate overrides. Surface metrics for Active/Deactivated schools and expose exports. | `/admin/schools`, `/admin/schools/create`, `/admin/schools/{school}`, `/admin/schools/{school}/edit`, `/admin/schools/export`                       | Schools PRD, Services module for catalog, SSA + Invoicing for downstream usage |
| Therapists                 | Maintain therapist profiles (employment, credentials, contact), configure per-service pay rates, toggle activation, and export caseload data.               | `/admin/therapists`, `/admin/therapists/create`, `/admin/therapists/{therapist}`, `/admin/therapists/{therapist}/rates`, `/admin/therapists/export` | Therapists PRD, Services catalog, Billing for payout rates                     |
| Students                   | Capture and update student demographics, guardians, schools, and timezone data. Manage activation states that feed SSA and scheduling.                      | `/admin/students`, `/admin/students/create`, `/admin/students/{student}`, `/admin/students/{student}/edit`, `/admin/students/export`                | Students PRD, Schools module, SSA/Scheduling modules                           |
| Service Support Agreements | Create/list SSAs with services, cadence, therapist assignment, and served minutes. Handle activation/completion/deactivation workflows.                     | `/admin/ssas`, `/admin/ssas/create`, `/admin/ssas/{ssa}`, `/admin/ssas/{ssa}/status`, `/admin/ssas/export`                                          | SSA PRD, Students, Therapists, Services modules                                |
| Services                   | Curate the canonical service catalog, documentation requirements, and default client/provider rates. Provide status controls and API exposure.              | `/admin/services`, `/admin/services/create`, `/admin/services/{service}`, `/admin/services/{service}/status`                                        | Services PRD, SSA + Scheduling for usage, Billing/Finance for rates            |

## Implementation Notes

1. **Navigation Components** – Update the admin layout (e.g., `resources/views/layouts/navigation.blade.php`) to replace any legacy labels (Registry/Directory/Catalog) with the names above. Ensure role middleware `auth` + `role:admin` protects every route.
2. **Information Architecture** – Breadcrumbs, page titles, and toast messages should reuse the same menu labels for consistency.
3. **Metrics Widgets** – Each index view referenced above needs summary metrics (active vs. inactive counts, served vs. authorized minutes) as called out in its PRD. Capture deltas as backlog items if missing.
4. **Exports & Permissions** – Export buttons must respect current filters and log actor IDs. Policy classes (`SchoolPolicy`, `TherapistProfilePolicy`, etc.) should guard destructive actions exposed from these menus.
5. **Testing** – Add feature tests that click each menu entry, assert the correct route renders, and verify unauthorized roles cannot access them.

## Backlog / Open Questions

-   Determine whether additional admin functions (Finance, Reporting) warrant separate top-level entries or live under these existing modules.
-   Decide if Schools/Therapists/Students need sub-navigation for Settings or History tabs before GA.
-   Confirm localization requirements for menu labels if the admin experience expands internationally.
