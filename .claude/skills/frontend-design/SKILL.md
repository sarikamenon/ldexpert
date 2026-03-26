---
name: frontend-design
description: Create distinctive, production-grade frontend interfaces with high design quality for the NOVA platform. Use when building Blade components, admin pages, form layouts, dashboard widgets, or any UI that needs to look polished and professional. Triggers on "design this page", "make this look better", "build a UI for", or when creating new Blade views.
---

# Frontend Design: NOVA UI Builder

Create distinctive, production-grade frontend interfaces for the NOVA therapy platform. All output must follow the NOVA design system defined in `app/docs/DESIGN_SYSTEM.md` and `CLAUDE.md`.

## Design Thinking

Before coding, understand the context:
- **Purpose**: What task does this interface serve? Who uses it (admin, therapist, student)?
- **Tone**: Professional, clean, modern — therapy platform for education professionals
- **Constraints**: Tailwind CSS only, design system colors, `x-ui::*` Blade components
- **Differentiation**: Clear visual hierarchy, one primary action per view

## NOVA Stack

- **Blade templates** with `x-ui::*` components
- **Tailwind CSS** using design system tokens only (`bg-primary`, `text-danger`, etc.)
- **Alpine.js** for reactive behavior
- **jQuery + Select2** for dropdowns and DataTables
- **SweetAlert2** for confirmations and alerts
- **Chart.js** (CDN) for data visualization

## Design Guidelines

### Layout
- Use `x-ui-card` for all content sections
- Card padding: `p-6`, section spacing: `mb-6`
- Responsive grid: stack on mobile, multi-column on desktop
- One primary action button per view (right-aligned in header)

### Typography
- H1: `text-2xl font-semibold text-foreground`
- H2: `text-lg font-semibold text-foreground`
- H3: `text-sm font-medium text-foreground/70`
- Body: `text-sm text-foreground`
- Labels: `text-xs font-medium text-foreground/70`

### Colors
- ONLY design system tokens: `bg-primary`, `bg-secondary`, `bg-success`, `bg-warning`, `bg-danger`
- NEVER hardcode hex (`bg-[#ff0000]`) or raw Tailwind palette (`bg-red-50`)
- Error states: `text-danger`
- Muted text: `text-foreground/60`

### Interactive States (MANDATORY)
Every interactive element must have:
- Default → Hover (`hover:bg-{variant}/90`) → Focus (`focus:ring-2 focus:ring-ring`) → Active (`active:bg-{variant}/80`) → Disabled (`disabled:opacity-50 disabled:pointer-events-none`)

### Forms (MANDATORY)
- All inputs MUST have help text before the input
- Pattern: `<x-input-label>` → `<p class="mt-1 text-xs text-foreground/60">` → `<x-text-input aria-describedby="...">` → `<x-input-error>`
- Use `x-ui::*` components for selects, checkboxes, radios

### Empty & Loading States
- Empty: `x-ui::empty-state` with `py-12`
- Loading: SweetAlert2 loading or button spinners
- Skeleton loaders for async content

### Feedback
- Success: `successToast()` from `resources/js/common/sweetalert.js`
- Error: `errorAlert()` from same module
- Destructive: `confirmDialog()` with consequence explanation
- Never use native `alert()`, `confirm()`, `prompt()`

## Quality Checklist

Before considering UI complete:
- [ ] Uses design system colors and typography scale
- [ ] Clear visual hierarchy with one primary action
- [ ] All states: default, loading, empty, error, success
- [ ] Form inputs have help text with `aria-describedby`
- [ ] Responsive on mobile, tablet, desktop
- [ ] Keyboard navigable
- [ ] Matches established patterns from existing pages
