# QA Tooling Setup

Dependencies required before running `/qa-create-scenarios` or `/qa-generate-tests`. Both skills read and write `qa/LD-Expert-QA.xlsx` and will fail silently on a fresh Claude Code installation without this setup.

---

## 1. Excel access — choose one path

Both skills try the Excel MCP server first, then fall back to the xlsx skill. At least one path must work.

---

### Path A — Anthropic xlsx skill (recommended — no install needed)

The `plugin:anthropic-skills:xlsx` skill ships with Claude Code's Anthropic skills plugin. It uses Python + openpyxl under the hood and requires no separate installation or configuration.

**How to confirm it is active:**

1. Open Claude Code in this project
2. Run `/qa-generate-tests`
3. If the skill reads sheet names from `qa/LD-Expert-QA.xlsx` without error, Path A is working

If it fails with "skill not found" or similar, the Anthropic skills plugin is not enabled in your Claude Code installation. Check your Claude Code subscription or contact your team lead — the plugin is a Claude Code feature, not a separate npm package.

---

### Path B — External Excel MCP server (optional enhancement)

An external MCP server named `excel` lets the skills read/write Excel files inline without spawning a sub-agent — faster than Path A. If your team has agreed on a specific MCP package, configure it in `.claude/settings.json` at the project root:

```json
{
  "mcpServers": {
    "excel": {
      "command": "npx",
      "args": ["-y", "<agreed-excel-mcp-package>"],
      "env": {}
    }
  }
}
```

Replace `<agreed-excel-mcp-package>` with the package your team uses. The server key **must** be named `excel` — the skills reference it by that exact name.

> `.claude/settings.json` is in `.gitignore`. Each developer configures it locally — it is never committed.

**If you do not have a team-agreed MCP package**, skip Path B entirely. Path A (the xlsx skill) covers the same functionality.

---

## 2. Verify Excel access works

Quick sanity check before running any QA skill:

1. Open Claude Code in this project root
2. Ask: `Read the sheet names from qa/LD-Expert-QA.xlsx`
3. Expected response: Claude lists the five sheets — `Admin`, `Therapist`, `Student`, `Finance`, `E2E` — without any tool error

If it fails, Path A is not active (see above). Do not proceed to run skills until this check passes.

---

## 3. GitHub Actions — BrowserQA on `main`

Pushes to `main` run [`.github/workflows/browser-qa.yml`](../.github/workflows/browser-qa.yml):

1. `docker compose up` → `scripts/ci/run-browser-qa.sh`
2. `migrate:fresh --seed --env=testing` on **`bird_test`**
3. `php artisan dusk tests/BrowserQA/`
4. Artifact: **`browserqa-dusk-html-report`** (`app/tests/ci-reports/dusk-report.html`)

Requires at least one `*BrowserTest.php` under `app/tests/BrowserQA/`.

---

## 4. Docker — required for all run commands

`/qa-smoke`, `/qa-admin`, `/qa-therapist`, `/qa-student`, `/qa-finance`, and `/qa-e2e` all run `php artisan dusk` inside Docker. No local PHP installation is needed.

**Verify Docker is up before any run command:**

```bash
docker compose ps
```

The `app` service must show status `Up`. If it is not running:

```bash
docker compose up -d
```

---

## Summary

| Dependency | Required for | How to get it |
|---|---|---|
| `plugin:anthropic-skills:xlsx` OR Excel MCP server | `/qa-create-scenarios`, `/qa-generate-tests` | See paths A and B above |
| Docker `app` container running | All `/qa-*` run commands | `docker compose up -d` |
