Run cross-role end-to-end workflow tests that span multiple user roles in a single flow. These verify that the full system works together, not just individual role features in isolation.

## Source files
- E2E workflow specs: `app/qa/e2e/student-journey.md`, `app/qa/e2e/therapist-session-to-billing.md`, `app/qa/e2e/admin-audit-flow.md`
- Test cases: `app/qa/LD-Expert-QA.xlsx` — E2E sheet
- Test data: `app/qa/e2e/test-data.md`
- Dusk tests: `tests/BrowserQA/E2E/`

> **Prerequisite 1:** If `app/tests/BrowserQA/E2E/` contains only `.gitkeep`, run `/qa-generate-tests` first to generate the PHP test files.
>
> **Prerequisite 2 (Recommended):** Before running the full suite, run `/qa-review-tests app/tests/BrowserQA/E2E/` to catch selector issues, missing assertions, or PHPStan violations. This prevents wasting 5–10 minutes waiting for false failures due to code quality issues.

## Steps

1. **Run the suite and publish the report** — this migrates the test DB (`bird_test`), runs the E2E Dusk suite, and writes a timestamped `.md` + `.html` report pair into the unified `app/qa/reports/` folder:
   ```bash
   bash scripts/qa/run-qa-report.sh e2e tests/BrowserQA/E2E/
   ```
   To run (and report on) a single workflow, pass a filter as an extra argument (each token separate, not one quoted string):
   ```bash
   bash scripts/qa/run-qa-report.sh e2e tests/BrowserQA/E2E/ --filter=QaStudentJourneyBrowserTest
   bash scripts/qa/run-qa-report.sh e2e tests/BrowserQA/E2E/ --filter=QaTherapistSessionToBillingBrowserTest
   bash scripts/qa/run-qa-report.sh e2e tests/BrowserQA/E2E/ --filter=QaAdminAuditFlowBrowserTest
   ```

2. **Workflows to validate**

   Full step-by-step details for each workflow are in the spec files:
   - `app/qa/e2e/student-journey.md` — Admin → Therapist → Student full flow
   - `app/qa/e2e/therapist-session-to-billing.md` — Session log → Bill → Payment → Ledger
   - `app/qa/e2e/admin-audit-flow.md` — Create → Edit → Deactivate → Audit trail

   Factory setup for each workflow is in `app/qa/e2e/test-data.md`.

3. **Report back** — the script prints the two generated paths (`app/qa/reports/e2e-YYYY-MM-DD-HHMM.md` and `.html`). Both are auto-generated from the run's JUnit output (summary counts + per-test table), so no hand-written result file is needed. Summarize which workflows passed/failed and, for any failure, name the failing step and the underlying cause.
