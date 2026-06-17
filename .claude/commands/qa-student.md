Run the full student QA suite — reads qa/student/ test plans, executes Student Dusk tests, and publishes a timestamped .md + .html report pair into qa/reports/.

## Source files
- Test plan: `qa/student/test-plan.md`
- Test cases: `qa/LD-Expert-QA.xlsx` — Student sheet
- Test data: `qa/student/test-data.md`
- Wiki PRDs: `app/wiki/student/portal.md`, `app/wiki/student/menu.md`

> **Prerequisite 1:** If `app/tests/BrowserQA/Student/` contains only `.gitkeep`, run `/qa-generate-tests` first to generate the PHP test files.
>
> **Prerequisite 2 (Recommended):** Before running the full suite, run `/qa-review-tests app/tests/BrowserQA/Student/` to catch selector issues, missing assertions, or PHPStan violations. This prevents wasting 5–10 minutes waiting for false failures due to code quality issues.

## Steps

1. **Run the suite and publish the report** — this migrates the test DB (`bird_test`), runs the Student Dusk suite, and writes a timestamped `.md` + `.html` report pair into the unified `qa/reports/` folder:
   ```bash
   bash scripts/qa/run-qa-report.sh student tests/BrowserQA/Student/
   ```

2. **Feature areas to validate** (cross-reference against qa/LD-Expert-QA.xlsx — Student sheet)

   | Area | Key actions to test |
   |------|-------------------|
   | Login | Valid login, invalid credentials, first-login password prompt |
   | Dashboard | Loads correctly, shows upcoming schedules, shows recent session data |
   | Upcoming schedules | Correct sessions shown, future dates only, correct therapist name |
   | Session history | Past approved sessions visible, correct dates in student timezone |
   | SSA Goals | Active goals listed, progress shown, mastered goals shown separately |
   | Empty states | Dashboard with no schedule, no goals, no sessions shows correct empty state |
   | Data isolation | Student cannot see another student's data |
   | Role isolation | Student cannot access /admin/* or /therapist/* routes |

3. **Report back** — the script prints the two generated paths (`qa/reports/student-YYYY-MM-DD-HHMM.md` and `.html`). Both are auto-generated from the run's JUnit output (summary counts + per-test table), so no hand-written result file is needed. Summarize the pass/fail totals and call out any failures or blockers.
