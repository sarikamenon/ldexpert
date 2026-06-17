Review the current staged/unstaged changes against CLAUDE.md project standards.

Steps:
1. Run `git diff` and `git diff --cached` to see all changes
2. Check each changed file against these CLAUDE.md rules:

   **PHP / Architecture**
   - `declare(strict_types=1)` present in PHP files
   - PHPStan Level 8 compliance (typed params, returns, generics on collections/relations)
   - Form Requests used for validation (no inline validation in controllers)
   - Controllers delegate to Services (no business logic in controllers)
   - Every controller action / side-effect (email, notifications) wrapped in try-catch (specific exceptions first, `\Throwable` fallback; swallow side-effects, re-throw primary-intent failures)
   - DTOs used for data transport, located under `app/DTOs/<Domain>/<Subdomain>/`
   - Policies added for new models
   - Soft deletes (`use SoftDeletes;` + `deleted_at`) on new models
   - File under 300 lines
   - `whereHas` preferred over `whereExists`/`DB::raw`; collection methods preferred over `foreach` for transforms
   - JSON list/object responses use an API Resource (not inline `response()->json([...])`)

   **Dates & Timezones** (see `app/docs/TIMEZONE_GUIDE.md`)
   - New timestamps stored in UTC; conversion via `UserTimezoneService` only (no hand-rolled TZ math, no `CONVERT_TZ` in SQL)
   - Date-range filters convert local bounds to UTC via `userDayUtcRange()` before `whereBetween` on a date column
   - Event-date columns (`session_date`/`schedule_date`) not TZ-shifted for display — date derived from the converted datetime
   - No calls to `viewerTimezone()` (not yet implemented)
   - User create/update DTOs exposing timezone include it in `toUserArray()`

   **Frontend / Blade**
   - Design system colors only (no hardcoded hex/tailwind colors)
   - Form inputs have help text with aria-describedby
   - SweetAlert2 used instead of native browser prompts
   - Vanilla JS for new code (no new jQuery); no inline `<script>` / `@php` data-shaping
3. Flag any violations with file:line references
4. Suggest fixes for each violation
5. Rate overall compliance: pass/needs-fixes/major-issues
