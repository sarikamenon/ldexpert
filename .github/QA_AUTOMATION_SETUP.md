# QA Automation — Setup & How It Works

Two GitHub Actions workflows automate QA from a merge: draft test scenarios →
human review → generate tests → run in `bird_test` → report → human review.

- `.github/workflows/qa-scenarios.yml` — **Stage 1** (author scenarios → PR #1)
- `.github/workflows/qa-tests.yml` — **Stage 2** (generate + run + report → PR #2)

They are **dormant by default** and do nothing until an admin completes the
setup below. Nothing runs, fails, or costs anything until then.

---

## The flow

```
Merge to main (wiki OR code)
   │  guard: nothing testable? → skip (green)
   ▼
Stage 1: /qa-create-scenarios → qa/LD-Expert-QA.xlsx + test-plan.md → PR #1 + email
   │  ── human reviews/edits/approves & MERGES PR #1 ──
   ▼
Stage 2: /qa-generate-tests → /qa-review-tests
   → run new + smoke + E2E in bird_test (CI container, safety-guarded)
   → self-heal locator/timeout failures once → report → PR #2 + email
   │  ── human: green → merge;  red → @claude fix (or dev fixes bug) → merge ──
   ▼
done
```

The human does exactly two things: **review/approve PR #1**, then **review/approve PR #2**.

---

## One-time setup (admin)

Requires **org/repo admin** on `TechUp-Labs/LD-Expert-Bird`.

### 1. Add the Claude auth token (secret)
The AI steps run Claude Code in CI. The workflows read **`CLAUDE_CODE_OAUTH_TOKEN`**
(a Claude Pro/Max subscription token — no separate API billing).

- On a machine logged into the company Claude Max plan, run:
  ```
  claude setup-token
  ```
  Copy the token it prints (starts with `sk-ant-oat...`).

Add it: **Settings → Secrets and variables → Actions → New repository secret**
- Name: `CLAUDE_CODE_OAUTH_TOKEN`
- Value: the token

> Prefer a pay-per-use **Anthropic API key** instead? Get one from the Anthropic
> console (starts with `sk-ant-api...`), name the secret `ANTHROPIC_API_KEY`, and
> change the `CLAUDE_CODE_OAUTH_TOKEN:` env lines in both workflows to `ANTHROPIC_API_KEY:`.

### 2. Add the config variables
**Settings → Secrets and variables → Actions → Variables → New repository variable**

| Variable | Value | Purpose |
|---|---|---|
| `QA_AUTOMATION_ENABLED` | `true` | **master switch** — until this is `true`, both workflows no-op |
| `QA_REVIEWER` | a GitHub username | tagged as reviewer on PR #1 / PR #2 |
| `QA_NOTIFY_EMAIL` | an email address | who gets the "ready for review" emails |

### 3. (Optional) Install the Claude GitHub App
For the `@claude fix …` comment workflow on PR #2:
- From Claude Code: `/install-github-app` → pick this repo, **or**
- install from https://github.com/apps/claude

### Email
The email steps reuse the existing `MAIL_USERNAME` / `MAIL_PASSWORD` secrets
already configured for `browser-qa.yml`. No new mail setup needed.

To **disable** everything later: set `QA_AUTOMATION_ENABLED=false` (or remove it).

---

## Safety guarantees (built into the workflows)

- **`bird_test` only.** Tests run via `scripts/ci/run-browser-qa.sh`, which forces
  `DB_DATABASE=bird_test` and **hard-aborts** if the DB is anything else. Runs in an
  isolated CI container — never a shared or production database.
- **QA files only.** After every Claude step a guard runs
  `git diff --name-only | grep -vE '^(app/tests/BrowserQA/|qa/)'` and **fails the
  run** if any non-QA file changed. The automation can only ever produce QA tests
  (`app/tests/BrowserQA/Qa*BrowserTest.php`) and QA artifacts (`qa/`). App code and
  developers' tests are never modified.
- **Skip when empty.** No testable change in the merge → Stage 1 skips cleanly.
- **Human gates.** No test code is generated until scenarios are approved (PR #1);
  nothing is permanent until the report is approved (PR #2).

---

## Notes / current limitations

- Stage 2 currently runs the **full** BrowserQA suite via `run-browser-qa.sh`
  (guarantees `bird_test` + reuses the verified setup). Scoping it to
  "new + smoke + E2E + changed role" for speed is a follow-up.
- The `claude -p` invocation and action versions may need a tweak on the first
  live run (CLI flags / action major versions evolve). The deterministic
  test-run half is the existing, verified script.
- Source priority: **wiki PRD when present**, else **code diff + merge message**.
  Code-only scenarios lean toward regression coverage — a rich PR/commit message
  improves intent coverage.
