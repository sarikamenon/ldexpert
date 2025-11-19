# NOVA · Contracts Module PRD

Version 1.0 · Last Updated: 18 Nov 2025

## 1. OVERVIEW

The Contracts module enables NOVA administrators to manage service contracts between the agency and schools (for invoicing) and between the agency and therapists (for billing). Each contract defines service rates, terms, and validity periods that drive downstream invoicing and billing processes.

## 2. OBJECTIVES

-   Provide separate contract management for schools and therapists
-   Define service-specific rates and rate types per contract
-   Enforce business rules (no overlapping active contracts, status management)
-   Support contract lifecycle management (create, edit, activate/deactivate)
-   Integrate with invoicing (school contracts) and billing (therapist contracts) modules

## 3. PERSONA & ROLE

**Persona:** System Admin | **Role:** Role::ADMIN | **Goals:** Create and manage contracts, set service rates, control contract status

## 4. FUNCTIONAL SCOPE

### 4.1 School Contracts

#### 4.1.1 Create School Contract

**Form Sections:**

-   **Contract Information**

    -   School\* (dropdown: select from active schools)
    -   Start Date\* (date picker)
    -   End Date\* (date picker, must be after start date)
    -   Notes (textarea, optional)
    -   Status\* (radio: Active, Inactive; default: Active)

-   **Service Rates** (repeatable section)
    -   Service\* (dropdown: select from active services)
    -   Rate\* (decimal, min: 0.00)
    -   Rate Type\* (radio: H=Hourly, F=Flat)
    -   Actions: Add Service, Remove Service

**Validation:**

-   No overlapping active contracts for the same school
-   At least one service must be added
-   Each service can only appear once per contract
-   End date must be after start date

**Actions:**

-   Create Contract (primary submit)
-   Cancel (secondary, returns to list)

#### 4.1.2 List School Contracts

**Summary Metrics:** Total Contracts | Active | Inactive

**Controls:**

-   Show Inactive checkbox (filter)
-   Search input (filter by school name, contract ID)
-   Export Contracts button
-   Add Contract button

**Table Columns:**

-   ID (sortable)
-   School Name (sortable, link to edit)
-   Start Date (sortable)
-   End Date (sortable)
-   Services Count (number of services in contract)
-   Status (sortable, badge: Active/Inactive)
-   Created At (sortable)
-   Actions (Edit, View Details, Toggle Status)

**Pagination:** Previous/Next, numbered pages, range label

#### 4.1.3 Edit School Contract

-   Same form as Create, pre-populated
-   Update Contract button replaces Create
-   Can modify dates, notes, services, rates
-   Status can be changed (with validation for overlapping)
-   No Reset button

#### 4.1.4 View School Contract Details

-   Display all contract information
-   List all services with rates and rate types
-   Show contract history/audit log (future enhancement)

### 4.2 Therapist Contracts

#### 4.2.1 Create Therapist Contract

**Form Sections:**

-   **Contract Information**

    -   Therapist\* (dropdown: select from active therapists)
    -   Start Date\* (date picker)
    -   End Date\* (date picker, must be after start date)
    -   Notes (textarea, optional)
    -   Status\* (radio: Active, Inactive; default: Active)

-   **Service Rates** (repeatable section)
    -   Service\* (dropdown: select from active services)
    -   Rate\* (decimal, min: 0.00)
    -   Rate Type\* (radio: H=Hourly, F=Flat)
    -   Actions: Add Service, Remove Service

**Validation:**

-   No overlapping active contracts for the same therapist
-   At least one service must be added
-   Each service can only appear once per contract
-   End date must be after start date

**Actions:**

-   Create Contract (primary submit)
-   Cancel (secondary, returns to list)

#### 4.2.2 List Therapist Contracts

**Summary Metrics:** Total Contracts | Active | Inactive

**Controls:**

-   Show Inactive checkbox (filter)
-   Search input (filter by therapist name, contract ID)
-   Export Contracts button
-   Add Contract button

**Table Columns:**

-   ID (sortable)
-   Therapist Name (sortable, link to edit)
-   Start Date (sortable)
-   End Date (sortable)
-   Services Count (number of services in contract)
-   Status (sortable, badge: Active/Inactive)
-   Created At (sortable)
-   Actions (Edit, View Details, Toggle Status)

**Pagination:** Previous/Next, numbered pages, range label

#### 4.2.3 Edit Therapist Contract

-   Same form as Create, pre-populated
-   Update Contract button replaces Create
-   Can modify dates, notes, services, rates
-   Status can be changed (with validation for overlapping)
-   No Reset button

#### 4.2.4 View Therapist Contract Details

-   Display all contract information
-   List all services with rates and rate types
-   Show contract history/audit log (future enhancement)

### 4.3 Status Management

-   Toggle status between Active/Inactive
-   When activating: check for overlapping active contracts (must deactivate existing first)
-   Confirmation dialog required for status changes
-   Status change reason optional (future enhancement)

## 5. USER EXPERIENCE GUIDELINES

-   Required fields marked with \* and validated inline
-   Date pickers with validation (end date > start date)
-   Loading overlays during save operations
-   Confirmation dialogs for status changes
-   Success toasts: "Contract created successfully", "Contract updated successfully"
-   Error messages displayed inline near offending fields
-   Service rate rows can be added/removed dynamically
-   Tables support keyboard navigation and visible focus states

## 6. DATA MODEL

### 6.1 School Contracts

**Table: `school_contracts`**

-   `id` (primary key)
-   `school_id` (foreign key → schools.id)
-   `start_date` (date)
-   `end_date` (date)
-   `notes` (text, nullable)
-   `status` (enum: active, inactive)
-   `created_at`, `updated_at`, `deleted_at` (soft deletes)

**Table: `school_contract_services`** (pivot)

-   `id` (primary key)
-   `school_contract_id` (foreign key → school_contracts.id)
-   `service_id` (foreign key → services.id)
-   `rate` (decimal 10,2)
-   `rate_type` (enum: H, F)
-   `created_at`, `updated_at`
-   Unique constraint: (school_contract_id, service_id)

**Indexes:**

-   `school_contracts.school_id`
-   `school_contracts.status`
-   `school_contracts.start_date`, `school_contracts.end_date` (for overlap checking)
-   `school_contract_services.school_contract_id`
-   `school_contract_services.service_id`

### 6.2 Therapist Contracts

**Table: `therapist_contracts`**

-   `id` (primary key)
-   `therapist_id` (foreign key → therapist_profiles.id)
-   `start_date` (date)
-   `end_date` (date)
-   `notes` (text, nullable)
-   `status` (enum: active, inactive)
-   `created_at`, `updated_at`, `deleted_at` (soft deletes)

**Table: `therapist_contract_services`** (pivot)

-   `id` (primary key)
-   `therapist_contract_id` (foreign key → therapist_contracts.id)
-   `service_id` (foreign key → services.id)
-   `rate` (decimal 10,2)
-   `rate_type` (enum: H, F)
-   `created_at`, `updated_at`
-   Unique constraint: (therapist_contract_id, service_id)

**Indexes:**

-   `therapist_contracts.therapist_id`
-   `therapist_contracts.status`
-   `therapist_contracts.start_date`, `therapist_contracts.end_date` (for overlap checking)
-   `therapist_contract_services.therapist_contract_id`
-   `therapist_contract_services.service_id`

## 7. ROUTES (INTERNAL WEB APP)

### School Contracts

-   `GET /admin/contracts/schools` — list view with metrics and filters
-   `GET /admin/contracts/schools/create` — creation form
-   `POST /admin/contracts/schools` — store new contract
-   `GET /admin/contracts/schools/{contract}/edit` — edit form
-   `PUT|PATCH /admin/contracts/schools/{contract}` — update contract
-   `GET /admin/contracts/schools/{contract}` — view details
-   `PATCH /admin/contracts/schools/{contract}/status` — toggle status
-   `GET /admin/contracts/schools/export` — export filtered results

### Therapist Contracts

-   `GET /admin/contracts/therapists` — list view with metrics and filters
-   `GET /admin/contracts/therapists/create` — creation form
-   `POST /admin/contracts/therapists` — store new contract
-   `GET /admin/contracts/therapists/{contract}/edit` — edit form
-   `PUT|PATCH /admin/contracts/therapists/{contract}` — update contract
-   `GET /admin/contracts/therapists/{contract}` — view details
-   `PATCH /admin/contracts/therapists/{contract}/status` — toggle status
-   `GET /admin/contracts/therapists/export` — export filtered results

## 8. VALIDATION RULES

### School/Therapist Contract

-   School/Therapist: required, must exist and be active
-   Start Date: required, valid date
-   End Date: required, valid date, must be after start date
-   Notes: optional, max 65535 characters
-   Status: required, must be 'active' or 'inactive'
-   Services: at least one service required
-   No overlapping active contracts for same school/therapist:
    -   When creating/activating: check if any active contract exists with overlapping date range
    -   Overlap condition: `(start_date <= new_end_date) AND (end_date >= new_start_date)`

### Contract Service

-   Service: required, must exist and be active
-   Rate: required, numeric, min: 0.00, max: 999999.99
-   Rate Type: required, must be 'H' (Hourly) or 'F' (Flat)
-   Unique: each service can only appear once per contract

## 9. BUSINESS RULES

1. **No Overlapping Active Contracts:**

    - A school/therapist can only have one active contract at a time
    - To create a new active contract, existing active contracts must be deactivated first
    - Overlap is determined by date ranges: `(start_date <= other_end_date) AND (end_date >= other_start_date)`

2. **Contract Editing:**

    - Contracts can be edited after creation
    - Editing dates may require re-checking for overlaps if status is active
    - Service rates can be added, removed, or modified

3. **Status Management:**

    - Only admins can change contract status
    - Activating a contract requires checking for overlaps
    - Deactivating a contract does not require overlap checks

4. **Service Rates:**

    - Each contract must have at least one service
    - Each service in a contract has its own rate and rate type
    - Rate types: H (Hourly) or F (Flat)

5. **Soft Deletes:**
    - Contracts use soft deletes to preserve historical data
    - Soft-deleted contracts are excluded from all queries by default

## 10. SECURITY & PERMISSIONS

-   Routes protected by `auth` middleware plus `role:admin` gate
-   Authorization policies ensure only admins can create/update/delete contracts
-   Policies: `ContractPolicy` for school contracts, `TherapistContractPolicy` for therapist contracts
-   Audit logging captures actor, timestamp, and summary of changes (future enhancement)

## 11. ACCESSIBILITY REQUIREMENTS

-   Forms support keyboard navigation, ARIA labels, and descriptive error messaging
-   Focus states clearly visible on all interactive elements
-   Table headers use `<th scope="col">`
-   Error messages linked via `aria-describedby`

## 12. FEEDBACK & MESSAGING

-   Success toasts: "Contract created successfully", "Contract updated successfully", "Contract status updated"
-   Error messages displayed inline near fields
-   Loading indicators during save operations
-   Confirmation dialogs for status changes
-   Validation errors shown on form submission

## 13. NON-FUNCTIONAL REQUIREMENTS

-   Performance: list views paginated; aim for <500ms response for common searches
-   Reliability: wrap writes in transactions to ensure contract + services persist atomically
-   Logging: capture failures with sufficient context for debugging
-   Data integrity: enforce unique constraints and foreign key relationships

## 14. DEPENDENCIES & INTEGRATIONS

-   **Services Module:** Supplies service catalog for contract service selection
-   **Schools Module:** Provides school list for school contract creation
-   **Therapists Module:** Provides therapist list for therapist contract creation
-   **Invoicing Module (Future):** Consumes school contracts for invoice generation
-   **Billing Module (Future):** Consumes therapist contracts for therapist bill generation

## 15. METRICS & REPORTING

-   Count of total vs. active vs. inactive contracts (displayed in summary metrics)
-   Contract coverage: number of schools/therapists with active contracts
-   Average contract duration
-   Service rate distribution

## 16. RISKS & OPEN QUESTIONS

-   Determine if contract versioning/history is needed for audit purposes (deferred to v2)
-   Clarify if contracts need approval workflow (deferred to v2)
-   Determine if contract templates are needed for common rate structures (deferred to v2)

## 17. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)

-   Contract history/audit trail with versioning
-   Contract approval workflow
-   Contract templates for common rate combinations
-   Contract expiration notifications with admin alerts
-   Contract renewal workflow
-   Advanced filtering (by date range, service, rate type)
-   Rate validation warnings vs. service defaults
