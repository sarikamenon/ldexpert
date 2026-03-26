Run pre-deployment checklist to verify the branch is ready to merge/deploy.

Steps:
1. **Git status**: Check for uncommitted changes, untracked files
2. **Branch info**: Show current branch, commits ahead of main, any merge conflicts
3. **PHPStan**: Run `make qa` and report errors (zero required)
4. **Tests**: Run `make test` and verify all pass
5. **Migrations**: Check for pending migrations (`php artisan migrate:status` via Docker)
6. **Assets**: Verify Vite manifest is up-to-date by checking if any JS/CSS files changed since last `make assets-build`
7. **New routes**: List any new routes added (compare with main branch)
8. **New dependencies**: Check for new composer/npm packages added
9. **Summary**: Pass/fail checklist with actionable items for any failures

Report format:
- [PASS] or [FAIL] for each check
- Total: X/Y checks passed
- If any FAIL, list what needs to be fixed before deployment
