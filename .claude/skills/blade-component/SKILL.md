---
name: blade-component
description: Create reusable Blade UI components for the NOVA design system. Use when building new UI components, extracting repeated UI patterns into components, or adding to the component library in resources/views/components/ui/. Triggers on "create a blade component", "extract this into a component", "add a UI component", "reusable component".
---

# Blade Component Builder

Create reusable Blade UI components following NOVA's design system and Laravel conventions.

## Component Location

All UI components go in `resources/views/components/ui/` and are used as `<x-ui::component-name>`.

## Component Structure

### Anonymous Component (preferred for simple UI)
```blade
{{-- resources/views/components/ui/badge.blade.php --}}
@props([
    'variant' => 'default',  // default, success, warning, danger, info
    'size' => 'sm',          // sm, md, lg
])

@php
$classes = match($variant) {
    'success' => 'bg-success/10 text-success',
    'warning' => 'bg-warning/10 text-warning',
    'danger' => 'bg-danger/10 text-danger',
    'info' => 'bg-primary/10 text-primary',
    default => 'bg-secondary/10 text-foreground/70',
};
$sizeClasses = match($size) {
    'md' => 'px-3 py-1 text-sm',
    'lg' => 'px-4 py-1.5 text-base',
    default => 'px-2 py-0.5 text-xs',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium {$classes} {$sizeClasses}"]) }}>
    {{ $slot }}
</span>
```

## Design System Rules

- ONLY use design system colors: `bg-primary`, `text-danger`, etc.
- NEVER hardcode hex or raw Tailwind palette colors
- All interactive elements need hover/focus/active/disabled states
- Use `$attributes->merge()` to allow class overrides
- Use `@props` for typed, documented props with defaults

## Common Components to Build

| Component | Usage |
|-----------|-------|
| `x-ui::badge` | Status indicators |
| `x-ui::card` | Content sections |
| `x-ui::empty-state` | No-data states |
| `x-ui::filter-toolbar` | DataTable filter forms |
| `x-ui::stat-card` | Dashboard metrics |
| `x-ui::status-badge` | Enum-based status display |
| `x-ui::action-button` | Table row actions |

## Accessibility Requirements

- Semantic HTML elements
- ARIA labels where meaning isn't obvious
- Keyboard navigable interactive elements
- Color is never the only state indicator
- Sufficient contrast ratios

## After Creating

1. Document the component in `app/docs/DESIGN_SYSTEM.md`
2. Test responsive behavior
3. Verify all interactive states work
