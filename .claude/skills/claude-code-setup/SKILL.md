---
name: claude-code-setup
description: Recommend and configure Claude Code automation — commands, skills, hooks, memory, and settings. Use when the user wants to optimize their Claude Code setup, add new commands or skills, configure hooks for automated workflows, or review their .claude/ folder structure. Triggers on "optimize my setup", "add a command", "configure hooks", "review my Claude Code config".
---

# Claude Code Setup Recommender

Analyze the current Claude Code configuration and recommend improvements for productivity.

## What to Analyze

1. **`.claude/` folder structure**
   - `commands/` — slash commands (`.md` files invoked via `/command-name`)
   - `skills/` — skills with `SKILL.md` (auto-triggered based on context)
   - `settings.local.json` — project-level permissions and settings

2. **`CLAUDE.md`** — project instructions (see claude-md-management skill)

3. **Memory** (`~/.claude/projects/<project>/memory/`)
   - `MEMORY.md` — index of memory files
   - Individual memory files (user, feedback, project, reference types)

4. **Global settings** (`~/.claude/settings.json`)
   - Permissions, enabled plugins

## Recommendation Categories

### Commands to Add
Look for repetitive multi-step workflows the user performs:
- QA pipelines → `/qa` command
- Code review → `/review` command
- Feature scaffolding → `/feature` command
- Deployment checks → `/deploy-check` command

### Skills to Add
Look for complex tasks that benefit from structured guidance:
- Domain-specific patterns (Laravel, PHPStan, DataTables)
- Design/UX standards enforcement
- Testing patterns

### Hooks to Configure
Automate responses to events:
- Pre-commit: run linting
- Post-tool-use: validate output
- Pre-push: run tests

### Permissions to Optimize
- Consolidate specific permissions into wildcards where safe
- Remove stale permissions for commands no longer used
- Add permissions for frequently-approved commands

## Output

Present recommendations as a prioritized list:
1. **High impact, low effort** — implement immediately
2. **High impact, high effort** — plan for next session
3. **Nice to have** — implement when convenient
