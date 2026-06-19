Run the full therapist QA suite — reads app/qa/therapist/ test plans, executes Therapist Dusk tests, and publishes a timestamped .md + .html report pair into app/qa/reports/.

## Source files
- Test plan: `app/qa/therapist/test-plan.md`
- Test cases: `app/qa/LD-Expert-QA.xlsx` — Therapist sheet
- Test data: `app/qa/therapist/test-data.md`
- Wiki PRDs: `app/wiki/therapist/*.md`

> **Prerequisite 1:** If `app/tests/BrowserQA/Therapist/` contains only `.gitkeep`, run `/qa-generate-tests` first to generate the PHP test files.
>
> **Prerequisite 2 (Recommended):** Before running the full suite, run `/qa-review-tests app/tests/BrowserQA/Therapist/` to catch selector issues, missing assertions, or PHPStan violations. This prevents wasting 5–10 minutes waiting for false failures due to code quality issues.

## Steps

1. **Run the suite and publish the report** — this migrates the test DB (`bird_test`), runs the Therapist Dusk suite, and writes a timestamped `.md` + `.html` report pair into the unified `app/qa/reports/` folder:
   ```bash
   bash scripts/qa/run-qa-report.sh therapist tests/BrowserQA/Therapist/
   ```

2. **Feature areas to validate** (cross-reference against app/qa/LD-Expert-QA.xlsx — Therapist sheet)

   | Area | Key actions to test |
   |------|-------------------|
   | Login & dashboard | Login, dashboard loads with correct schedule summary |
   | Schedule management | Create single session, create recurring (daily/weekly/monthly), edit, cancel |
   | Session log submission | Submit from schedule, submit standalone, fill all fields, DRAFT → SUBMITTED |
   | Session log sent back | Therapist sees SENT_BACK status, edits and resubmits |
   | SSA goals | View goals, mark mastered, mark discontinued, view progress |
   | Student comments | Add comment, view history, edit own comment |
   | Student documents | Upload document, view document list |
   | Paystub report | View paystub, download PDF, filter by year |
   | Data isolation | Therapist cannot see another therapist's students/sessions/schedules |
   | Role isolation | Therapist cannot access /admin/* routes |

3. **Report back** — the script prints the two generated paths (`app/qa/reports/therapist-YYYY-MM-DD-HHMM.md` and `.html`). Both are auto-generated from the run's JUnit output (summary counts + per-test table), so no hand-written result file is needed. Summarize the pass/fail totals and call out any failures or blockers; if you found and fixed bugs during the run, note them in your reply.
