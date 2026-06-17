Run the full finance QA suite — reads qa/finance/ test plans, executes Finance Dusk tests, and publishes a timestamped .md + .html report pair into qa/reports/.

> **Finance is not a separate user role.** There is no `Role::FINANCE` enum and no `User::factory()->finance()` state. Finance is a feature module within the admin dashboard. All Finance tests log in with `User::factory()->admin()->create()`. Role isolation tests in the Finance suite verify that therapist and student accounts are blocked from `/admin/invoices/*`, `/admin/billing/*`, `/admin/payments/*`, `/admin/ledger/*`, and `/admin/expenses/*`.

## Source files
- Test plan: `qa/finance/test-plan.md`
- Test cases: `qa/LD-Expert-QA.xlsx` — Finance sheet
- Test data: `qa/finance/test-data.md`
- Wiki PRDs: `app/wiki/finance/*.md`

> **Prerequisite 1:** If `app/tests/BrowserQA/Finance/` contains only `.gitkeep`, run `/qa-generate-tests` first to generate the PHP test files.
>
> **Prerequisite 2 (Recommended):** Before running the full suite, run `/qa-review-tests app/tests/BrowserQA/Finance/` to catch selector issues, missing assertions, or PHPStan violations. This prevents wasting 5–10 minutes waiting for false failures due to code quality issues.

## Steps

1. **Run the suite and publish the report** — this migrates the test DB (`bird_test`), runs the Finance Dusk suite, and writes a timestamped `.md` + `.html` report pair into the unified `qa/reports/` folder:
   ```bash
   bash scripts/qa/run-qa-report.sh finance tests/BrowserQA/Finance/
   ```

2. **Feature areas to validate** (cross-reference against qa/finance/test-plan.md)

   | Area | Key actions to test |
   |------|-------------------|
   | Invoices | Generate (DRAFT), send (→ SENT), record payment (→ PAID) |
   | Therapist bills | Generate (DRAFT), send (→ SENT), delete (DRAFT only), record payment (→ PAID) |
   | Payments | Manual payment against invoice, manual payment against bill, Stripe link generation |
   | Ledger | View account transactions, balance_after chain integrity, CSV export |
   | Expenses | Create, edit, delete, total summary updates |
   | Pay stub report | Year filter, PDF download, data isolation (therapist sees only own) |
   | Billing automation | Configure schedule, manual trigger, run history |
   | Role isolation | Therapist/student cannot access finance routes |

3. **Post-run ledger verify**
   ```bash
   docker compose exec -T app bash -lc 'php artisan ledger:verify'
   ```
   Must return zero drift errors. If any found, call them out in your report-back.

4. **Report back** — the script prints the two generated paths (`qa/reports/finance-YYYY-MM-DD-HHMM.md` and `.html`). Both are auto-generated from the run's JUnit output (summary counts + per-test table), so no hand-written result file is needed. Summarize the pass/fail totals, include the `ledger:verify` result (PASS/FAIL + any drift errors), and call out any blockers.
