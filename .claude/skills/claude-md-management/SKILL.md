---
name: claude-md-management
description: Analyze, improve, and maintain CLAUDE.md project instructions. Use when the user wants to review CLAUDE.md for quality, add new conventions, resolve contradictions, optimize for context window efficiency, or keep project instructions up to date. Triggers on "improve CLAUDE.md", "update project rules", "check our conventions", or when CLAUDE.md needs maintenance.
---

# CLAUDE.md Management

Analyze and improve CLAUDE.md project instructions for clarity, completeness, and context efficiency.

## When to Use

- User asks to review or improve CLAUDE.md
- New conventions need to be added after a pattern emerges
- Contradictions are found between CLAUDE.md rules and actual code
- CLAUDE.md is growing too large (target: under 300 lines for fast loading)

## Analysis Steps

1. **Read current CLAUDE.md** and measure line count
2. **Check for contradictions** between rules (e.g., "always X" in one section, "never X" in another)
3. **Check for redundancy** — rules stated multiple times in different sections
4. **Check for staleness** — rules that reference patterns no longer in the codebase
5. **Check for missing conventions** — patterns in the code that aren't documented
6. **Assess context efficiency** — verbose sections that could reference external docs

## Optimization Strategies

### Keep in CLAUDE.md (always loaded)
- Architecture rules (Controller → Service → Repository)
- Hard constraints (PHPStan L8, strict types, soft deletes)
- Patterns that affect every file (use statements, typing, DTOs)
- Role/middleware rules
- Docker/command conventions

### Extract to app/docs/ (loaded on demand)
- Detailed code examples (PHPStan patterns, Blade templates)
- Design system specifications (colors, typography scales)
- DataTables migration patterns
- Testing patterns with examples

### Structure Rules
- Lead with the rule, not the context
- One rule per bullet point
- Group by when-you-encounter-this, not by-category
- Use bold for the imperative, plain text for the reason

## Output

Present a report with:
1. Current stats (line count, section count)
2. Issues found (contradictions, redundancy, staleness)
3. Proposed changes (with diffs)
4. Expected impact (line count reduction, clarity improvement)
