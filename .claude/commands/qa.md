Run the full QA pipeline (Pint + PHPStan + Pest) via Docker and summarize any errors.

Steps:
1. Run `make qa` inside Docker
2. If PHPStan fails, categorize errors by type (missing return types, undefined properties, argument mismatches, etc.) and list the top 10 files with most errors
3. If Pest fails, list failing tests with their error messages
4. If Pint has fixes, list the files that were changed
5. Provide a clear summary: total errors per tool, and suggested fix priority
