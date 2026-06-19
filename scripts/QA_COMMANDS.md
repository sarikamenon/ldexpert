# QA Test Commands — LD Expert Bird

Single source of truth for running the QA browser tests, the QA slash-commands
(`.claude/commands/qa*`), and the QA skills (`.claude/skills/qa*`).

> **Two rules that must always hold:**
> 1. Always pass **`--env=testing`** — it uses the safe `bird_test` database, never production `bird`.
> 2. **Docker must be running** — `docker compose up -d`.

> **Platform note:** The `docker compose ...` commands are **identical** on
> Windows (PowerShell or CMD), macOS, and Linux — Docker normalizes them. Where a
> command genuinely differs by OS (shortcuts, report script, `curl`), separate
> **Windows** and **macOS / Linux** blocks are shown.

---

## 0. Start Docker (all platforms)

```bash
docker compose up -d
```

---

## 1. Running Dusk Tests Directly (Docker)

✅ **These are identical on Windows, macOS, and Linux.** The container WORKDIR is
already `/var/www/html/app`, so the short form works.

### By role / suite

```bash
# Admin
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin --env=testing

# Therapist
docker compose exec -T app php artisan dusk tests/BrowserQA/Therapist --env=testing

# Student
docker compose exec -T app php artisan dusk tests/BrowserQA/Student --env=testing

# Finance
docker compose exec -T app php artisan dusk tests/BrowserQA/Finance --env=testing

# E2E (cross-role)
docker compose exec -T app php artisan dusk tests/BrowserQA/E2E --env=testing

# ALL suites
docker compose exec -T app php artisan dusk tests/BrowserQA --env=testing
```

### A single test file

```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin/QaAdminCoreBrowserTest.php --env=testing
```

### A single test case (by TC ID)

```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin/QaAdminCoreBrowserTest.php --filter=TC-A001 --env=testing
```

### Long form (equivalent — explicit cd, e.g. to add a timeout)

```bash
docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/Admin/ --env=testing'
```

---

## 2. Running with Reports (.md + .html in `app/qa/reports/`)

> ### 🛑 DATA-LOSS WARNING — `run-qa-report.sh` WIPES `bird_test`
> This script runs `php artisan migrate:fresh --seed` **before and after** the
> tests — it **drops and recreates every table in `bird_test`**. Any data you
> have in `bird_test` will be **permanently destroyed**.
>
> - ✅ Fine if `bird_test` is a disposable test DB (the intended design).
> - ❌ **Do NOT run this** if you keep real/important data in `bird_test` — back
>   it up first, or use the **safe** report path instead:
>   ```powershell
>   .\make.ps1 qa-admin        # Windows — runs run-suite-docker.sh: reports WITHOUT migrate:fresh
>   ```
>   ```bash
>   docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh admin tests/BrowserQA/Admin/
>   ```
>   `run-suite-docker.sh` produces the same `.md` + `.html` reports but uses
>   targeted cleanup (deletes only `qa.`/`QA `-prefixed rows) — it does **not**
>   wipe the database.

This calls into Docker and also generates an Allure dashboard.

**Windows** (run from **Git Bash** or **WSL** — not PowerShell, since it's a `.sh` script):
```bash
bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/
```

**macOS / Linux** (Terminal):
```bash
bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/
```

Other suites (same on all platforms — swap the two arguments):
```bash
bash scripts/qa/run-qa-report.sh therapist tests/BrowserQA/Therapist/
bash scripts/qa/run-qa-report.sh student   tests/BrowserQA/Student/
bash scripts/qa/run-qa-report.sh finance   tests/BrowserQA/Finance/
bash scripts/qa/run-qa-report.sh e2e       tests/BrowserQA/E2E/
```

Output:
```
app/qa/reports/admin-YYYY-MM-DD-HHMM.md     ← Markdown summary
app/qa/reports/admin-YYYY-MM-DD-HHMM.html   ← Open in browser
```

### Open the generated HTML report

**Windows (PowerShell):**
```powershell
start (Get-ChildItem app/qa/reports/*.html | Sort-Object LastWriteTime | Select-Object -Last 1).FullName
```

**macOS:**
```bash
open "$(ls -t app/qa/reports/*.html | head -1)"
```

**Linux:**
```bash
xdg-open "$(ls -t app/qa/reports/*.html | head -1)"
```

---

## 3. Shortcuts

### Windows — PowerShell functions (`scripts/qa-functions.ps1`)

Load once per PowerShell window (note the leading **dot + space**):
```powershell
. .\scripts\qa-functions.ps1
```
Then:
```powershell
qa-admin       # tests/BrowserQA/Admin/
qa-therapist   # tests/BrowserQA/Therapist/
qa-e2e         # tests/BrowserQA/E2E/
qa-smoke       # tests/BrowserQA/Smoke/ — critical-path check (~3 min)
qa-all         # tests/BrowserQA/ — everything
qa-help        # show available commands
```
> Only these 6 exist — there is **no `qa-student` / `qa-finance`** shortcut
> (use the §1 Docker commands for those).

Make permanent (add to your PowerShell profile):
```powershell
notepad $PROFILE
# add this line (use your real path), save, restart PowerShell:
. "C:\LD-Expert-Bird-main (2)\LD-Expert-Bird-main\scripts\qa-functions.ps1"
```
If you get "running scripts is disabled":
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### macOS / Linux — shell aliases (no `.ps1`; create your own)

There is no bundled bash shortcut file, but you can paste these into your
`~/.bashrc` or `~/.zshrc` (run `source ~/.zshrc` after, or open a new terminal):
```bash
alias qa-admin='docker compose exec -T app php artisan dusk tests/BrowserQA/Admin --env=testing'
alias qa-therapist='docker compose exec -T app php artisan dusk tests/BrowserQA/Therapist --env=testing'
alias qa-student='docker compose exec -T app php artisan dusk tests/BrowserQA/Student --env=testing'
alias qa-finance='docker compose exec -T app php artisan dusk tests/BrowserQA/Finance --env=testing'
alias qa-e2e='docker compose exec -T app php artisan dusk tests/BrowserQA/E2E --env=testing'
alias qa-all='docker compose exec -T app php artisan dusk tests/BrowserQA --env=testing'
```

---

## 3b. `make` Wrapper — **Windows only** (also generates reports)

The per-suite QA targets (`qa-admin`, `qa-therapist`, …) live **only in the
Windows `make.bat`** (wrapped by `make.ps1`). Every QA target runs the in-Docker
report script, so it produces the `.md` + `.html` reports automatically (unlike
§1, which only runs the tests).

**Windows (PowerShell):**
```powershell
.\make.ps1 qa-admin
```
**Windows (CMD):**
```cmd
make.bat qa-admin
```

Available QA targets:
```
qa-admin            All admin tests + report
qa-admin-core       Just QaAdminCoreBrowserTest.php
qa-admin-billing    Just QaAdminBillingBrowserTest.php
qa-admin-sessions   Just QaAdminSessionsBrowserTest.php
qa-therapist        Therapist suite
qa-student          Student suite
qa-finance          Finance suite
qa-e2e              E2E suite
qa-smoke / qa-quick Smoke suite (~3 min)
qa-browser / qa-debug   All browser tests (~45 min)
qa-fresh            migrate:fresh --seed, THEN all tests  ⚠️ wipes bird_test
```
Infra targets: `up`, `down`, `restart`, `build`, `migrate`, `fresh`, `seed`,
`test`, `cache-clear`, `assets-build`.

> ⚠️ `qa-fresh` runs `migrate:fresh --seed` on `bird_test` — it **wipes the test
> database** before running. The other QA targets use targeted cleanup and do not.

### ❗ macOS / Linux — `make qa-admin` does NOT exist

The Unix `Makefile` does **not** define the per-suite QA targets — only generic
ones (`make qa` = Pint+PHPStan+Pest, `make dusk`, `make migrate`, `make up`, …).
So `make qa-admin` will fail with *"No rule to make target"*. On macOS / Linux,
run a suite with the direct Docker command (§1) or the report script (§2):
```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin --env=testing
# or, with reports:
bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/
```

---

## 4. QA Slash-Commands — `.claude/commands/qa*`

**Claude Code slash-commands.** Run them inside an interactive Claude session by
typing `/<name>` (same on every OS). Each reads the role's test plan, runs the
Dusk suite, and publishes a `.md` + `.html` report into `app/qa/reports/`.

| Command | What it does |
|---------|--------------|
| `/qa` | Full code-quality pipeline (`make qa` = Pint + PHPStan + Pest) and error summary. **Not** a browser suite. |
| `/qa-admin` | Full Admin QA suite — reads `app/qa/admin/` plans, runs Admin Dusk tests, publishes report. |
| `/qa-therapist` | Full Therapist QA suite. |
| `/qa-student` | Full Student QA suite. |
| `/qa-finance` | Full Finance QA suite (billing/invoicing module of admin; not a separate role). |
| `/qa-e2e` | Cross-role end-to-end workflows. |
| `/qa-smoke` | Fast critical-path check across all roles (~3 min). Run first. |

---

## 5. QA Skills — `.claude/skills/qa*`

**Claude Code skills**, invoked inside a Claude session by `/<skill-name>` or a
trigger phrase (same on every OS). They author/maintain test cases and files —
they do **not** run the browser suites themselves.

| Skill | Purpose | Example trigger |
|-------|---------|-----------------|
| `/qa-create-scenarios` | Plan full coverage from wiki PRDs → `test-plan.md`, `test-data.md`, Excel rows (incremental). | "create test plan", "plan QA for admin" |
| `/qa-add-test-cases` | Add test cases directly to `app/qa/LD-Expert-QA.xlsx` in the correct format. | "add test cases to excel" |
| `/qa-generate-tests` | Convert Excel test cases → PHP Dusk files under `app/tests/BrowserQA/`. | "generate browser tests" |
| `/qa-review-tests` | Review generated tests for selector/assertion/PHPStan issues before a full run. | "review tests `app/tests/BrowserQA/Admin/`" |
| `/qa-test-strategy` | Coordinate execution, coverage mapping, Excel sync. | "test strategy", "sync excel test cases" |

### Typical authoring → running flow
```
/qa-create-scenarios   →  plan coverage + write Excel rows
/qa-generate-tests     →  Excel → PHP Dusk files
/qa-review-tests       →  sanity-check generated files
# then run them in a terminal (any OS):
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin --env=testing
```

---

## 6. Database Safety

All QA commands use `--env=testing` → database **`bird_test`** (production `bird`
is never touched). But **not all commands are equal** — some wipe `bird_test`:

### ✅ SAFE — targeted cleanup only (your real data in `bird_test` survives)
`QaDuskTestCase` does **not** run `migrate:fresh`; it deletes only
`qa.`-prefixed users / `QA `-prefixed schools, preserving the seeded admin
(`develop.ldexpert@gmail.com`) and everything else.
- `.\make.ps1 qa-admin` (and all `make.bat` `qa-*` targets → `run-suite-docker.sh`)
- `docker compose exec ... php artisan dusk ... --env=testing`
- the `qa-admin` PowerShell shortcut / bash aliases

### 🛑 DESTRUCTIVE — runs `migrate:fresh`, wipes ALL of `bird_test`
- `bash scripts/qa/run-qa-report.sh <suite> <path>`  (migrate:fresh before **and** after)
- `.\make.ps1 qa-fresh` / `make.bat qa-fresh`
- `.\make.ps1 fresh` / `make.bat fresh` / `make fresh`
- `make erd` / `make erd-check`
- CI (nightly, staging server): `scripts/ci/run-browser-qa-staging.sh`
- CI (Docker fallback, manual only): `scripts/ci/run-browser-qa.sh`

> **`bird_test` is intended as a disposable test database.** Keep real/important
> data in `bird` (production), never in `bird_test`. If you must keep data in
> `bird_test`, back it up before running anything in the destructive list, and
> avoid commands that have school names starting `QA ` or emails starting `qa.`
> (the safe cleanup would delete those too).

---

## 7. Troubleshooting

### Every test fails / app returns 500 on every page
Usually a broken `app/.env` (DB unreachable inside Docker). Quick check:

**Windows (PowerShell):**
```powershell
curl.exe -s -o NUL -w "HTTP %{http_code}`n" http://localhost:8080/login
```
**macOS / Linux:**
```bash
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080/login
```
If `500`, ensure `app/.env` has the Docker values (not stock Laravel defaults):
```
DB_HOST=mysql        # NOT 127.0.0.1
DB_USERNAME=bird     # NOT root
DB_PASSWORD=secret
SESSION_DRIVER=file  # NOT database
CACHE_STORE=file     # NOT database
APP_URL=http://localhost:8080
DB_DATABASE=bird_test
```
Then (all platforms):
```bash
docker compose exec -T app php artisan config:clear
```

### `BadMethodCallException: Call to undefined method ...Factory::qa()`
The `qa()` factory states must exist on `UserFactory` and `SchoolFactory`
(used by `QaDuskTestCase::createQaUser()` / `createQaSchool()`).

### Docker connection failed
```bash
docker compose up -d
```

### Tests time out (all platforms)
```bash
docker compose exec -T app bash -lc 'cd /var/www/html/app && timeout 1800 php artisan dusk tests/BrowserQA/Admin/ --env=testing'
```

---

## 8. Test & File Structure

| Test Suite | PHP location (Docker path) |
|-----------|----------------------------|
| Admin | `tests/BrowserQA/Admin/` |
| Therapist | `tests/BrowserQA/Therapist/` |
| Student | `tests/BrowserQA/Student/` |
| Finance | `tests/BrowserQA/Finance/` |
| E2E | `tests/BrowserQA/E2E/` |

```
scripts/
├── qa-functions.ps1        ← Windows PowerShell shortcuts (qa-admin, …)
├── QA_COMMANDS.md          ← This file
└── app/qa/
    ├── run-qa-report.sh    ← Runs a suite + generates .md/.html reports
    └── run-suite-docker.sh ← In-container worker (Chrome + Dusk + reports)

.claude/
├── commands/qa*.md         ← Slash-commands: /qa-admin, … (run a suite + report)
└── skills/qa*/             ← Skills: /qa-generate-tests, … (author/maintain tests)

app/qa/
├── LD-Expert-QA.xlsx       ← Master test-case workbook (one sheet per role)
└── reports/                ← Generated .md + .html report pairs
```

---

**Last Updated:** 2026-06-18
**Status:** Production Ready ✅
