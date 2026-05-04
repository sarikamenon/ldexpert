NOVA · Settings & Configuration PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Settings module provides admin-configurable system settings, catalog management for positions, service aliases, and expense categories. These are foundational reference data used across the platform.

2. FUNCTIONAL SCOPE

   2.1 System Settings
   Route: GET/PUT /admin/settings
   Configurable fields:
   - Site Name (string)
   - Support Email (email)
   - Records Per Page (10-100)
   - Maintenance Mode (boolean)
   - SMTP Host, Port, Username, Password
   Service: `SettingsService` — `getAllGroups()`, `updateSettings()`

   2.2 Positions (Therapist Roles)
   Routes: /admin/positions/*
   Full CRUD with server-side DataTable.
   - Name, description, linked services (many-to-many)
   - Status: active/inactive (PositionStatus enum)
   - Export to CSV
   - Therapists must have a position to serve a given service type
   Controller: `PositionController`
   Service: `PositionCatalogService`
   Policy: `PositionPolicy`

   2.3 Service Aliases (Import Mapping)
   Routes: /admin/service-aliases/*
   Full CRUD with server-side DataTable.
   - Maps external service names (from RSM/MARVIN imports) to internal Service models
   - Used during SSA import to resolve service names automatically
   - Can be deleted (only entity with hard delete in settings)
   Controller: `ServiceAliasController`
   Policy: `ServiceAliasPolicy`

   2.4 Expense Categories
   Routes: /admin/settings/expense-categories/*
   Full CRUD with server-side DataTable.
   - Name, description, status (active/inactive toggle)
   - Used by Expense tracking module
   Controller: `ExpenseCategoryController`
   Service: `ExpenseCategoryService`

3. NAVIGATION
   All settings items appear under the "Settings" top-level admin menu:
   - Services → service catalog management
   - Expense Categories → expense category CRUD
   - Positions → therapist position/role catalog
   - Service Aliases → import alias mapping

4. ROUTES
   System Settings:
   - GET /admin/settings — settings form
   - PUT /admin/settings — update settings

   Positions:
   - GET /admin/positions — list
   - POST /admin/positions/data — DataTable endpoint
   - GET /admin/positions/create — create form
   - POST /admin/positions — store
   - GET /admin/positions/{position}/edit — edit form
   - PUT /admin/positions/{position} — update
   - PATCH /admin/positions/{position}/status — toggle status
   - GET /admin/positions/export — CSV export

   Service Aliases:
   - GET /admin/service-aliases — list
   - POST /admin/service-aliases/data — DataTable endpoint
   - GET /admin/service-aliases/create — create form
   - POST /admin/service-aliases — store
   - GET /admin/service-aliases/{serviceAlias}/edit — edit form
   - PUT /admin/service-aliases/{serviceAlias} — update
   - DELETE /admin/service-aliases/{serviceAlias} — delete

   Expense Categories:
   - GET /admin/settings/expense-categories — list
   - POST /admin/settings/expense-categories/data — DataTable endpoint
   - GET /admin/settings/expense-categories/create — create form
   - POST /admin/settings/expense-categories — store
   - GET /admin/settings/expense-categories/{expenseCategory}/edit — edit form
   - PUT /admin/settings/expense-categories/{expenseCategory} — update
   - PATCH /admin/settings/expense-categories/{expenseCategory}/toggle-status — toggle
