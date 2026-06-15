---
name: dusk-pest-best-practices
description: Core conventions, timezone display logic, layout requirements, and wait helpers for writing Laravel Dusk tests. Triggers on "dusk best practices", "pest conventions", "dusk rules".
disable-model-invocation: true
---

# Dusk & Pest Best Practices Checklist

When automating browser tests for LD Expert Bird, always adhere to these coding and architecture standards:

---

## 1. Syntax & Typing Rules

*   **Pest PHP Only:** Never use PHPUnit classes. Use top-level `it()` or `test()` with closure arguments.
*   **Strict Types:** Every PHP test file must start with `declare(strict_types=1);`.
*   **Imports:** Always import models, enums, and helpers at the top of the file via `use` statements. Do not use fully qualified namespaces inline.
*   **Type Hinting:** Ensure any parameters in helper closures (such as `Browser $browser`) are strictly typed.

---

## 2. Arrange (Database State Setup)

*   **Always Use Factories:** Set up preconditions using model factories. Never write raw SQL inserts inside tests.
*   **Factory Alignment:** When creating profiles, always create their parents (e.g. creating a `School` and a `User` before creating `StudentProfile` or `TherapistProfile`).
*   **Database Isolation:** 
    *   **Feature/Unit Tests** (`tests/Feature/*`, `tests/Unit/*`): Use `RefreshDatabase` trait (included in `TestCase` base class). Wraps each test in a transaction and rolls back automatically.
    *   **Developer Dusk Tests** (`app/tests/Browser/*`): Use `DatabaseMigrations` trait (or override `runDatabaseMigrations()` to run `migrate:fresh` per test for isolation).
    *   **QA Dusk Tests** (`app/tests/BrowserQA/*`): Extend `QaDuskTestCase` which uses selective cleanup (`cleanUpQaTestData()`) — safe for staging/production-like environments.

---

## 3. Date & Timezone Handling (MANDATORY)

*   **UTC in Database:** All date-time data is saved as UTC in the database.
*   **Viewer Timezone Display:** For browser verification, date-times must be converted to the logged-in user's timezone using `UserTimezoneService`.
*   **Display Formatting:**
    *   Times must match `config('display.time')` (e.g., `9:30 AM`).
    *   Date-times must match `config('display.datetime')` (e.g., `May 23, 2026 9:30 AM`).
    *   Do not hardcode formatting strings like `'Y-m-d H:i'` when asserting visible text in the browser.

---

## 4. UI Component Interactions

### A. SweetAlert2 Confirmation Dialogs
*   SweetAlert2 alerts are not native browser alerts.
*   Wait for the alert container: `->waitFor('.swal2-container')`
*   Type into SweetAlert text inputs: `->type('.swal2-input', 'Reason text')`
*   Confirm: `->click('button.swal2-confirm')`
*   Dismiss: `->click('button.swal2-cancel')`

### B. DataTables
*   DataTables load content asynchronously.
*   Always await list table rendering: `->waitFor('table.dataTable tbody tr')`
*   When filtering or searching, wait for processing to finish before asserting: `->waitUntilMissing('.dataTables_processing')`

### C. Modals
*   Modals show/hide via CSS class switching.
*   Wait for visibility: `->waitFor('.modal-container')` or `->assertVisible('.modal-container')`.

---

## 5. Security & Isolation Tests

*   **Role Isolation:** Every test file must contain at least one test verifying that the current logged-in role is blocked from accessing unauthorized sections (returns a 403 or redirects).
    ```php
    it('student cannot access admin schools', function (): void {
        $student = User::factory()->student()->create();
        $this->browse(function (Browser $browser) use ($student): void {
            $browser->loginAs($student)
                ->visit('/admin/schools')
                ->assertPathIsNot('/admin/schools'); // Blocked/redirected
        });
    });
    ```
*   **Data Isolation:** Verify that a user cannot see or edit another user's resources (e.g., Student A cannot see Student B's schedule logs).

---

## 6. QA vs. Developer Test Separation (MANDATORY)

*   **Never Modify Developer Tests:** Do not modify, edit, or delete any developer-authored test files (like `ExampleTest.php`, `LoginTest.php`, or files lacking the `Qa` prefix).
*   **QA Directory and Filename Prefixing:**
    *   Place all QA-designed browser tests in role subdirectories nested under a dedicated `QA/` namespace:
        *   `tests/BrowserQA/Admin/`
        *   `tests/BrowserQA/Therapist/`
        *   `tests/BrowserQA/Student/`
        *   `tests/BrowserQA/Finance/`
        *   `tests/BrowserQA/E2E/`
    *   Every QA-authored test filename must start with `Qa` and end with `BrowserTest.php` (e.g., `QaAdminSchoolsBrowserTest.php`).
*   **Taking Logic Inspiration:** Use developer-added tests for reference (e.g., check how they mock schools, handle DataTables Ajax waits, or authenticate user states). Port that logic pattern over to construct clean, separate QA-prefixed test files instead of modifying developer files.
