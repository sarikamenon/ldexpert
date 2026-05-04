---
name: plugin-dev
description: Guide for developing Claude Code plugins including skills, commands, hooks, and MCP integrations. Use when the user wants to understand how Claude Code extensibility works, create a new plugin, or learn the difference between skills and commands. Triggers on "how do skills work", "create a plugin", "what's the difference between commands and skills".
---

# Claude Code Plugin Development Guide

## Architecture Overview

```
.claude/
├── commands/           # Slash commands (user-invoked via /name)
│   └── *.md           # Each file = one command
├── skills/            # Skills (auto-triggered by context)
│   └── skill-name/
│       ├── SKILL.md   # Required — frontmatter + instructions
│       ├── scripts/   # Optional — executable helpers
│       ├── references/# Optional — docs loaded on demand
│       └── assets/    # Optional — templates, icons
└── settings.local.json # Permissions, hooks
```

## Commands vs Skills

| Aspect | Commands | Skills |
|--------|----------|--------|
| Invocation | User types `/command-name` | Auto-triggered by context |
| File | `commands/name.md` | `skills/name/SKILL.md` |
| Arguments | `$ARGUMENTS` placeholder | N/A |
| Frontmatter | None | Required (name, description) |
| Best for | Explicit workflows | Implicit expertise |

## Creating a Command

Create a `.md` file in `.claude/commands/`:

```markdown
Do the thing the user wants.

Arguments: $ARGUMENTS contains the user's input after the command name.

Steps:
1. First step
2. Second step
3. Third step
```

## Creating a Skill

Create a `SKILL.md` with YAML frontmatter:

```markdown
---
name: my-skill
description: What it does and when to trigger. Be specific about trigger contexts.
---

# Skill Title

Instructions for Claude when this skill is active.
```

### Skill Description Tips
- Include both what the skill does AND when to use it
- List specific trigger phrases
- Be slightly "pushy" — Claude tends to under-trigger skills
- Include edge cases and near-misses

### Skill Size Guidelines
- SKILL.md: under 500 lines (loaded into context when triggered)
- Large reference docs: put in `references/` subdirectory
- Repetitive scripts: put in `scripts/` subdirectory

## Creating Hooks

Add to `settings.local.json`:

```json
{
  "hooks": {
    "PostToolUse": [{
      "matcher": "Edit",
      "hooks": [{"type": "command", "command": "your-command"}]
    }]
  }
}
```

## MCP Integrations

Claude Code supports MCP servers for external tool access. Configure in settings or via `claude mcp add`.
