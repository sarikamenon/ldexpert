Run a fast smoke test across all roles to verify the application is alive and all critical paths work. Run this first before any deeper QA suite. Completes in ~3 minutes.

> **Prerequisite 1:** If `app/tests/BrowserQA/` directories contain only `.gitkeep` files, run `/qa-generate-tests smoke` first to generate the smoke test PHP files.
>
> **Prerequisite 2 (Recommended):** Before running the smoke suite, run `/qa-review-tests app/tests/BrowserQA/` to catch selector issues, missing assertions, or PHPStan violations in smoke tests. This prevents wasting time on false failures.

## Steps

1. **Run the smoke suite and publish the report** — this migrates the test DB (`bird_test`), runs every `@group smoke` test across all roles, and writes a timestamped `.md` + `.html` report pair into the unified `qa/reports/` folder:
   ```bash
   bash scripts/qa/run-qa-report.sh smoke tests/BrowserQA/ --group=smoke
   ```

2. **Critical paths to verify across all roles**

   | Role | Action | Expected |
   |------|--------|----------|
   | Guest | Visit `/` | Redirects to `/login` |
   | Guest | Submit wrong credentials | Validation error shown |
   | Admin | Login → `/admin/dashboard` | Dashboard loads, nav visible |
   | Admin | Visit `/admin/students` | DataTable renders |
   | Admin | Visit `/admin/therapists` | DataTable renders |
   | Admin | Visit `/admin/schools` | DataTable renders |
   | Therapist | Login → `/therapist/dashboard` | Dashboard loads |
   | Therapist | Visit `/therapist/schedule-calendar` | Calendar renders |
   | Student | Login → `/student/dashboard` | Dashboard loads |
   | Admin | Visit `/therapist/dashboard` | 403 or redirect to admin |
   | Therapist | Visit `/admin/students` | 403 or redirect |
   | Student | Visit `/admin/dashboard` | 403 or redirect |

3. **Report back** — the script prints the two generated paths (`qa/reports/smoke-YYYY-MM-DD-HHMM.md` and `.html`), both auto-generated from the run's JUnit output. Summarize the result inline, e.g.:
   ```
   Smoke — 6/7 passed, 1 blocker (Admin student list: DataTable 500). Report: qa/reports/smoke-YYYY-MM-DD-HHMM.md
   ```

4. **If any check fails** — stop. A failing smoke test signals a deployment or migration issue. Fix before running deeper suites.
