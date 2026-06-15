Run the full admin QA suite — reads qa/admin/ test plans, executes Admin Dusk tests, and publishes a timestamped .md + .html report pair into qa/reports/.

## Source files
- Test plan: `qa/admin/test-plan.md`
- Test cases: `qa/LD-Expert-QA.xlsx` — Admin sheet
- Test data: `qa/admin/test-data.md`
- Wiki PRDs: `app/wiki/admin/*.md`

> **Prerequisite 1:** If `app/tests/BrowserQA/Admin/` contains only `.gitkeep`, run `/qa-generate-tests` first to generate the PHP test files.
>
> **Prerequisite 2 (Recommended):** Before running the full suite, run `/qa-review-tests app/tests/BrowserQA/Admin/` to catch selector issues, missing assertions, or PHPStan violations. This prevents wasting 5–10 minutes waiting for false failures due to code quality issues.

## Steps

1. **Run the suite and publish the report** — this migrates the test DB (`bird_test`), runs the Admin Dusk suite, and writes a timestamped `.md` + `.html` report pair into the unified `qa/reports/` folder:
   ```bash
   bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/
   ```

2. **Feature areas to validate** (cross-reference against qa/LD-Expert-QA.xlsx — Admin sheet)

   | Area | Key actions to test |
   |------|-------------------|
   | Schools | Create, edit, view, activate/deactivate, contract assignment |
   | Students | Create, edit, activate/deactivate, export, import CSV |
   | Therapists | Create, edit, activate/deactivate, export, contract assignment |
   | SSA | Create, assign therapist, view goals, activate/deactivate |
   | Session Logs | View list, approve, send back, filter by status |
   | Invoices | Create, send, record payment, view ledger |
   | Therapist Bills | Create, send, delete, record payment |
   | Imports | Upload CSV, validate, import students/SSAs/session logs |
   | Reports | SSA utilization, caseload, expirations |
   | Role isolation | Admin cannot access /therapist/* or /student/* |

3. **Report back** — the script prints the two generated paths (`qa/reports/admin-YYYY-MM-DD-HHMM.md` and `.html`). Both are auto-generated from the run's JUnit output (summary counts + per-test table), so no hand-written result file is needed. Summarize the pass/fail totals and call out any failures or blockers. Failure screenshots, if any, are under `app/tests/BrowserQA/screenshots/`.

## Reports

After the test run, **two reports are generated** from the same JUnit output:

| Report | Path | View |
|--------|------|------|
| **Markdown** | `qa/reports/admin-YYYY-MM-DD-HHMM.md` | Read in editor or Markdown viewer |
| **HTML** | `qa/reports/admin-YYYY-MM-DD-HHMM.html` | Double-click to open in browser |
