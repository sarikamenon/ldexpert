Review the current staged/unstaged changes against CLAUDE.md project standards.

Steps:
1. Run `git diff` and `git diff --cached` to see all changes
2. Check each changed file against these CLAUDE.md rules:
   - `declare(strict_types=1)` present in PHP files
   - PHPStan Level 8 compliance (typed params, returns, generics on collections/relations)
   - Form Requests used for validation (no inline validation in controllers)
   - Controllers delegate to Services (no business logic in controllers)
   - DTOs used for data transport between layers
   - Policies added for new models
   - Soft deletes on new models
   - File under 300 lines
   - Design system colors only (no hardcoded hex/tailwind colors)
   - Form inputs have help text with aria-describedby
   - SweetAlert2 used instead of native browser prompts
3. Flag any violations with file:line references
4. Suggest fixes for each violation
5. Rate overall compliance: pass/needs-fixes/major-issues
