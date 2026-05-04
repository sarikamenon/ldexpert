---
name: hookify
description: Create and manage Claude Code hooks — automated shell commands that execute in response to events like tool calls, notifications, and user prompts. Use when the user wants to automate workflows like "run linting after every edit", "check tests before commit", or "validate PHPStan after PHP changes". Triggers on "add a hook", "automate this", "run X after every Y", "when I do X automatically do Y".
---

# Hookify: Claude Code Hook Manager

Create and manage hooks in `settings.json` or `settings.local.json` that automate workflows.

## Hook Events

| Event | When it fires |
|-------|--------------|
| `PreToolUse` | Before a tool executes |
| `PostToolUse` | After a tool completes |
| `Notification` | When a background task completes |
| `Stop` | When Claude finishes a response |
| `SubagentStop` | When a subagent completes |

## Hook Structure

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit",
        "hooks": [
          {
            "type": "command",
            "command": "echo 'File edited: $TOOL_INPUT_FILE_PATH'"
          }
        ]
      }
    ]
  }
}
```

## Common NOVA Project Hooks

### Run Pint after PHP file edits
```json
{
  "matcher": "Edit",
  "hooks": [{
    "type": "command",
    "command": "if echo $TOOL_INPUT_FILE_PATH | grep -q '\\.php$'; then docker compose exec -T app bash -lc 'cd app && vendor/bin/pint --dirty'; fi"
  }]
}
```

### Rebuild assets after JS/CSS changes
```json
{
  "matcher": "Write",
  "hooks": [{
    "type": "command",
    "command": "if echo $TOOL_INPUT_FILE_PATH | grep -qE '\\.(js|css)$'; then make assets-build; fi"
  }]
}
```

### Validate PHPStan on Stop
```json
{
  "matcher": "Stop",
  "hooks": [{
    "type": "command",
    "command": "make qa 2>&1 | tail -5"
  }]
}
```

## Implementation Steps

1. Ask the user what they want to automate
2. Identify the right event and matcher
3. Write the hook command
4. Add to `settings.local.json` (project-level) or `~/.claude/settings.json` (global)
5. Test the hook by triggering the event
