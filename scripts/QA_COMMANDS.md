# QA Test Commands - LD Expert Bird

Quick reference for running QA browser tests using Dusk.

## Quick Start

### Option 1: One-Time Setup (Permanent)
```powershell
powershell -ExecutionPolicy Bypass -File scripts/qa-setup.ps1
# Then restart PowerShell
```

### Option 2: Temporary (Current Session Only)
```powershell
. .\scripts\qa-functions.ps1
```

## Available Commands

After setup or loading functions, use these commands:

### Run Admin Tests
```powershell
qa-admin
```
Executes: `php artisan dusk tests/BrowserQA/Admin/ --env=testing`

**Duration:** ~15 minutes

---

### Run Therapist Tests
```powershell
qa-therapist
```
Executes: `php artisan dusk tests/BrowserQA/Therapist/ --env=testing`

**Duration:** ~20 minutes

---

### Run E2E Tests
```powershell
qa-e2e
```
Executes: `php artisan dusk tests/BrowserQA/E2E/ --env=testing`

**Duration:** ~10 minutes

---

### Run Smoke Tests (Fast)
```powershell
qa-smoke
```
Executes: `php artisan dusk tests/BrowserQA/Smoke/ --env=testing`

**Tests critical paths:**
- Login with valid credentials
- Dashboard loads after login
- Role isolation (users blocked from other roles)

**Duration:** ~3 minutes

---

### Run All Browser Tests
```powershell
qa-all
```
Executes: `php artisan dusk tests/BrowserQA/ --env=testing`

**Includes:** All admin, therapist, student, finance, and E2E tests

**Duration:** ~45 minutes

---

### Display Help
```powershell
qa-help
```

Shows available commands and setup instructions

---

## Manual Commands (Without Setup)

If you prefer not to set up functions, use Docker directly:

```powershell
# Admin tests
docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/Admin/ --env=testing'

# Therapist tests
docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/Therapist/ --env=testing'

# E2E tests
docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/E2E/ --env=testing'

# All tests
docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/ --env=testing'
```

---

## Database Safety

All QA commands use:
- **Environment:** `--env=testing`
- **Database:** `bird_test` (test database)
- **Impact:** Safe - isolated from production database `bird`

Each test run:
1. Uses fresh database (`migrate:fresh`)
2. Creates test data via factories
3. Cleans up after completion
4. Does NOT affect production data

---

## Troubleshooting

### PowerShell: "cannot be loaded because running scripts is disabled"
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Functions not found after restart
Load them manually:
```powershell
. .\scripts\qa-functions.ps1
```

### Docker connection failed
Ensure Docker is running:
```powershell
docker compose up -d
```

### Tests timeout
Some tests take 15-20 minutes. Increase timeout if needed:
```powershell
docker compose exec -T app bash -lc 'cd /var/www/html/app && timeout 1800 php artisan dusk tests/BrowserQA/Admin/ --env=testing'
```

---

## File Structure

```
scripts/
├── qa-functions.ps1      ← QA command functions
├── qa-setup.ps1          ← Setup automation script
└── QA_COMMANDS.md         ← This file
```

---

## For Team Members

### First Time Setup
1. Clone the repository
2. Run setup once:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/qa-setup.ps1
   ```
3. Restart PowerShell
4. Use commands: `qa-admin`, `qa-therapist`, `qa-e2e`, etc.

### Every Time
Just use the short commands:
```powershell
qa-admin      # Run admin tests
qa-therapist  # Run therapist tests
qa-e2e        # Run E2E tests
qa-smoke      # Quick sanity check
qa-all        # Run everything
```

---

## CI/CD Integration

GitHub Actions automatically runs:
- Smoke tests (daily schedule or workflow_dispatch)
- Browser QA suite (on push/PR)
- Generates HTML reports

See `.github/workflows/browser-qa.yml` for automation details.

---

## Test Structure

Test cases are defined in `qa/LD-Expert-QA.xlsx` and organized by role:

| Test Suite | Location |
|-----------|----------|
| Admin | `tests/BrowserQA/Admin/` |
| Therapist | `tests/BrowserQA/Therapist/` |
| Student | `tests/BrowserQA/Student/` |
| Finance | `tests/BrowserQA/Finance/` |
| E2E | `tests/BrowserQA/E2E/` |
| Smoke | `tests/BrowserQA/Smoke/` |

See `qa/LD-Expert-QA.xlsx` for current test cases and coverage details.

---

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review test case documentation in `qa/docs/`
3. Check GitHub Actions logs for CI/CD failures
4. Review Dusk test output in terminal

---

**Last Updated:** 2026-06-10  
**Version:** 1.0  
**Status:** Production Ready ✅
