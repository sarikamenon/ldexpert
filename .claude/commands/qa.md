Run the developer code quality pipeline (Pint + PHPStan + Pest) via Docker and summarize any errors.

> **Note:** This is NOT the browser test runner. To run QA browser tests use `/qa-admin`, `/qa-therapist`, `/qa-student`, `/qa-finance`, `/qa-e2e`, or `/qa-smoke` instead.

Steps:
1. Run `make qa` inside Docker
2. If PHPStan fails, categorize errors by type (missing return types, undefined properties, argument mismatches, etc.) and list the top 10 files with most errors
3. If Pest fails, list failing tests with their error messages
4. If Pint has fixes, list the files that were changed
5. Provide a clear summary: total errors per tool, and suggested fix priority
