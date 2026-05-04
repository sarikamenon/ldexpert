---
name: playground
description: Creates interactive HTML playgrounds — self-contained single-file explorers that let users configure something visually through controls, see a live preview, and copy out configuration or code. Use when the user asks to make a playground, explorer, interactive tool, or visual configurator for testing UI components, color schemes, or form layouts.
---

# Playground Builder

A playground is a self-contained HTML file with interactive controls on one side, a live preview on the other, and an output section with a copy button. The user adjusts controls, explores visually, then copies the generated output.

## When to use

When the user asks for an interactive playground, explorer, or visual tool — especially for:
- Testing Tailwind color combinations
- Previewing Blade component configurations
- Exploring DataTable column layouts
- Testing form help text patterns
- Visualizing design system tokens

## Core Requirements

- **Single HTML file.** Inline all CSS and JS. No external dependencies except Tailwind CDN.
- **Live preview.** Updates instantly on every control change.
- **Output section.** Generated code (Blade, Tailwind classes, config) with copy button.
- **Sensible defaults + presets.** Looks good on first load. Include 3-5 named presets.
- **Dark theme.** System font for UI, monospace for code output.

## State Management

```javascript
const state = { /* all configurable values */ };
function updateAll() {
  renderPreview();
  updateOutput();
}
// Every control calls updateAll() on change
```

## Output Pattern

Generate actionable output — Blade component code, Tailwind classes, or configuration that can be pasted directly into the NOVA project.

## After Building

Save to `_local_docs/playgrounds/` and run `open <filename>.html` to launch in browser.
