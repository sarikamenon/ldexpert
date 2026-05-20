# NOVA Design System Documentation

This document serves as the single source of truth for the NOVA design system. It documents both the current state of the UI implementation and the standards that should be followed going forward.

## Table of Contents

1. [Color System](#color-system)
2. [Typography Scale](#typography-scale)
3. [Spacing System](#spacing-system)
4. [Component Patterns](#component-patterns)
5. [Interactive States](#interactive-states)
6. [Layout Standards](#layout-standards)
7. [Accessibility Guidelines](#accessibility-guidelines)
8. [Empty & Loading States](#empty--loading-states)
9. [Typography Standards](#typography-standards)
10. [Spacing Standards](#spacing-standards)
11. [Component Standards](#component-standards)
12. [Interactive State Standards](#interactive-state-standards)
13. [Design Quality Checklist](#design-quality-checklist)

---

## Color System

### Current State

The color system is defined in `tailwind.config.js` with the following palette:

#### Primary Colors

- **Primary**: `#5563b8` (Blue-purple) - Primary brand color
  - Tailwind: `bg-primary`, `text-primary`, `border-primary`
  - Usage: Primary actions, branding, main CTAs
  - Foreground: `#ffffff` (white text on primary background)
  - Shades: 50-900 available (e.g., `bg-primary-50`, `bg-primary-900`)

- **Secondary**: `#14b8a6` (Teal) - Secondary brand color
  - Tailwind: `bg-secondary`, `text-secondary`, `border-secondary`
  - Usage: Secondary actions, supporting elements
  - Foreground: `#ffffff` (white text on secondary background)
  - Shades: 50-900 available

- **Accent**: `#a855f7` (Purple) - Accent brand color
  - Tailwind: `bg-accent`, `text-accent`, `border-accent`
  - Usage: Highlights, accents, decorative elements
  - Foreground: `#ffffff` (white text on accent background)
  - Shades: 50-900 available

#### Semantic Colors

- **Success**: `#22c55e` (Green)
  - Tailwind: `bg-success`, `text-success`, `border-success`
  - Usage: Success states, positive feedback
  - Foreground: `#ffffff`

- **Warning**: `#f59e0b` (Amber)
  - Tailwind: `bg-warning`, `text-warning`, `border-warning`
  - Usage: Warning states, cautionary messages
  - Foreground: `#111827` (dark text on warning background)

- **Danger**: `#ef4444` (Red)
  - Tailwind: `bg-danger`, `text-danger`, `border-danger`
  - Usage: Error states, destructive actions, validation errors
  - Foreground: `#ffffff`

#### Background Colors

- **Background Default**: `#ffffff` (White)
  - Tailwind: `bg-background`
  - Usage: Main page background

- **Background Muted**: `#f6f7f8` (Light gray)
  - Tailwind: `bg-background/muted`
  - Usage: Subtle background variations

- **Background Subtle**: `#f2f4f7` (Very light gray)
  - Tailwind: `bg-background/subtle`
  - Usage: Hover states, table row backgrounds, subtle sections

#### Foreground Colors

- **Foreground Default**: `#0b1220` (Dark blue-black)
  - Tailwind: `text-foreground`
  - Usage: Primary text color

- **Foreground Muted**: `#475569` (Medium gray)
  - Tailwind: `text-foreground/muted` or `text-foreground/60`
  - Usage: Secondary text, labels, muted content

#### Border & Input Colors

- **Border**: `#e5e7eb` (Light gray)
  - Tailwind: `border-border`
  - Usage: Borders, dividers

- **Input**: `#e5e7eb` (Light gray)
  - Tailwind: `border-input`
  - Usage: Input field borders

- **Ring**: `#5563b8` (Primary blue-purple)
  - Tailwind: `ring-ring`
  - Usage: Focus rings on interactive elements

#### Gradient Colors

- **Gradient Nova**: `linear-gradient(135deg, #5563b8 0%, #14b8a6 50%, #a855f7 100%)`
  - Tailwind: `bg-gradient-nova`
  - Usage: Brand gradients, hero sections

- **Gradient Nova Primary**: `linear-gradient(135deg, #5563b8 0%, #a855f7 100%)`
  - Tailwind: `bg-gradient-nova-primary`
  - Usage: Simplified brand gradient

### Color Usage Guidelines

**DO:**
- Use primary color for main actions and CTAs
- Use secondary color for supporting actions
- Use semantic colors (success, warning, danger) for their intended states
- Use foreground/background color system for text hierarchy
- Use opacity modifiers (e.g., `text-foreground/60`) for muted text

**DON'T:**
- Use ad-hoc colors (e.g., `bg-[#ff0000]`)
- Use hardcoded hex values in views
- Mix color systems (e.g., don't use `bg-gray-100` when `bg-background/subtle` is available)
- Use colors that aren't in the design system

### Current Inconsistencies

- **Alert Component**: Uses hardcoded colors (`bg-green-50`, `bg-red-50`, `text-green-700`, `text-red-700`) instead of design system colors
- **Badge Component**: Uses `bg-gray-100` instead of design system colors
- **Input Error Component**: Uses `text-red-600` instead of `text-danger`

---

## Typography Scale

### Current State

#### Font Family

- **Primary Font**: Inter, Figtree, system sans-serif
  - Defined in `tailwind.config.js` as `font-sans`
  - Usage: All text content

#### Current Typography Usage

**Headings:**
- **H1 (Page Titles)**: `text-2xl font-semibold text-foreground`
  - Example: Page headers, main titles
  - Size: 1.5rem (24px)
  - Weight: 600 (semibold)

- **H2 (Section Titles)**: `text-lg font-semibold text-foreground`
  - Example: Card section headers, major sections
  - Size: 1.125rem (18px)
  - Weight: 600 (semibold)

- **H3 (Subsection Titles)**: `text-sm font-medium text-foreground/70` or `text-base font-semibold`
  - Example: Form section labels, subsection headers
  - Size: 0.875rem (14px) or 1rem (16px)
  - Weight: 500 (medium) or 600 (semibold)

**Body Text:**
- **Default**: `text-sm text-foreground`
  - Example: Most content, paragraphs, table cells
  - Size: 0.875rem (14px)
  - Weight: 400 (normal)

- **Large**: `text-base text-foreground`
  - Example: Important content, emphasized text
  - Size: 1rem (16px)
  - Weight: 400 (normal)

- **Small**: `text-xs text-foreground/60`
  - Example: Labels, captions, helper text
  - Size: 0.75rem (12px)
  - Weight: 400 (normal)

**Font Weights:**
- **Normal (400)**: Default body text
- **Medium (500)**: Labels, emphasis, subsection titles
- **Semibold (600)**: Headings, strong emphasis

### Current Inconsistencies

- Mixed use of `text-sm`, `text-base`, `text-lg`, `text-2xl` without clear pattern
- Inconsistent font weights (`font-medium` vs `font-semibold` for similar elements)
- Some headings use `font-medium` when they should use `font-semibold`
- Inconsistent use of muted text (`text-foreground/60` vs `text-foreground/70`)

---

## Spacing System

### Current State

#### Spacing Scale

The spacing system uses Tailwind's default spacing scale (4px base unit):

- **Tight (2)**: `gap-2`, `space-y-2`, `p-2`, `m-2` (8px)
  - Usage: Tightly related elements, icon + text pairs

- **Normal (4)**: `gap-4`, `space-y-4`, `p-4`, `m-4` (16px)
  - Usage: Standard spacing between elements, form field groups

- **Loose (6)**: `gap-6`, `space-y-6`, `p-6`, `m-6` (24px)
  - Usage: Section separation, card padding

- **Extra Loose (8)**: `gap-8`, `space-y-8`, `p-8`, `m-8` (32px)
  - Usage: Major page sections

#### Current Spacing Patterns

**Card Padding:**
- Most cards use: `p-6` (24px)
- Some cards use: `p-4` (16px) - inconsistent

**Section Spacing:**
- Between major sections: `mb-6` (24px)
- Some sections use: `mb-4` (16px) or `py-10` (40px) or `py-12` (48px) - inconsistent

**Form Field Spacing:**
- Form groups: `space-y-4` or `gap-4` (16px)
- Some forms use: `space-y-6` (24px) - inconsistent

**Button Padding:**
- Standard buttons: `px-4 py-2` (16px horizontal, 8px vertical)
- Small buttons: `px-3 py-2` (12px horizontal, 8px vertical)
- Large buttons: `px-5 py-2` (20px horizontal, 8px vertical)

**Grid Gaps:**
- Standard grid: `gap-4` (16px)
- Some grids use: `gap-6` (24px) - inconsistent

### Current Inconsistencies

- Mixed use of `mb-4`, `mb-6`, `py-10`, `py-12` without clear pattern
- Inconsistent spacing between form fields
- Inconsistent card padding (`p-6` vs `p-4`)
- Some empty states use `py-10`, others use `py-12` - no standard

---

## Component Patterns

### Current State

#### Button Component (`resources/views/components/ui/button.blade.php`)

**Variants:**
- `primary`: `bg-primary text-primary-foreground hover:bg-primary/90`
- `secondary`: `bg-background text-foreground border border-border hover:bg-background/subtle`
- `ghost`: `bg-transparent text-foreground hover:bg-background/muted`
- `success`: `bg-success text-success-foreground hover:bg-success/90`
- `danger`: `bg-danger text-danger-foreground hover:bg-danger/90`

**Sizes:**
- `sm`: `h-8 px-3 text-sm` (32px height)
- `md`: `h-9 px-4 text-sm` (36px height) - default
- `lg`: `h-10 px-5 text-base` (40px height)

**States:**
- Default: Base variant styling
- Hover: `hover:bg-{variant}/90` or `hover:bg-background/subtle`
- Focus: `focus:outline-none focus:ring-2 focus:ring-ring`
- Disabled: `disabled:opacity-50 disabled:pointer-events-none`
- Active: **Missing** - no `active:` states defined

**Usage:**
```blade
<x-ui::button variant="primary" size="md">Click Me</x-ui::button>
```

#### Card Component (`resources/views/components/ui/card.blade.php`)

**Styling:**
- Background: `bg-white`
- Border: `border border-border`
- Border Radius: `rounded-lg`
- Shadow: `shadow-sm`
- Padding: Applied via `class` attribute (typically `p-6`)

**Usage:**
```blade
<x-ui::card class="p-6">
    Content here
</x-ui::card>
```

#### Badge Component (`resources/views/components/ui/badge.blade.php`)

**Variants:**
- `primary`: `bg-primary/10 text-primary border border-primary/20`
- `secondary`: `bg-gray-100 text-gray-700 border border-gray-200` - **Uses hardcoded colors**
- `muted`: `bg-muted/50 text-foreground/50 border border-border` — low-emphasis variant for inactive, dismissed, or "no-op" states (e.g. an empty sub-coverage cell). Reads as visibly de-emphasized next to the colored variants.
- `success`: `bg-success/10 text-success border border-success/20`
- `warning`: `bg-warning/10 text-warning border border-warning/20`
- `danger`: `bg-danger/10 text-danger border border-danger/20`
- `info`: `bg-blue-100 text-blue-700 border border-blue-200` - **Uses hardcoded colors**

**Styling:**
- Padding: `px-2 py-0.5`
- Border Radius: `rounded-base` (0.5rem)
- Font: `text-xs font-medium`

**Usage:**
```blade
<x-ui::badge variant="primary">Active</x-ui::badge>
```

#### Alert Component (`resources/views/components/ui/alert.blade.php`)

**Variants:**
- `info`: `bg-background/subtle text-foreground border border-border`
- `success`: `bg-green-50 text-green-700 border border-green-200` - **Uses hardcoded colors**
- `warning`: `bg-yellow-50 text-yellow-800 border border-yellow-200` - **Uses hardcoded colors**
- `danger`: `bg-red-50 text-red-700 border border-red-200` - **Uses hardcoded colors`

**Styling:**
- Padding: `p-3`
- Border Radius: `rounded-base` (0.5rem)

**Usage:**
```blade
<x-ui::alert variant="success">Success message</x-ui::alert>
```

#### Input Component (`resources/views/components/ui/input.blade.php`)

**Styling:**
- Base: `block w-full rounded-base border border-input bg-white px-3 py-2`
- Text: `text-foreground`
- Placeholder: `placeholder:text-foreground/50`
- Focus: `focus:ring-2 focus:ring-ring focus:border-ring`

**Usage:**
```blade
<x-ui::input type="text" name="email" />
```

#### Table Component (`resources/views/components/ui/table.blade.php`)

**Styling:**
- Container: `overflow-hidden border border-border rounded-lg bg-white`
- Table: `w-full text-left text-sm border-collapse border border-border`
- Header: `bg-background/subtle text-foreground`

**Usage:**
```blade
<x-ui::table>
    <x-slot name="head">
        <tr><th>Header</th></tr>
    </x-slot>
    <tr><td>Content</td></tr>
</x-ui::table>
```

#### DataTable Component (`resources/views/components/ui/datatable.blade.php`)

**Styling:**
- Similar to table component
- Includes DataTables.js initialization via JavaScript
- ID attribute for DataTables targeting

**Usage:**
```blade
<x-ui::datatable id="myTable">
    <x-slot name="head">
        <tr><th>Header</th></tr>
    </x-slot>
    <tr><td>Content</td></tr>
</x-ui::datatable>
```

#### Select Component (`resources/views/components/ui/select.blade.php`)

**Styling:**
- Base: `block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm`
- Text: `text-foreground`
- Placeholder: `placeholder:text-foreground/50`
- Focus: `focus:ring-2 focus:ring-primary focus:border-primary`

**Features:**
- Supports Select2 integration via `data-select-box` attribute
- Configurable: searchable, multiple, tags, etc.

**Usage:**
```blade
<x-ui::select name="school_id" :searchable="true">
    <option value="">Select...</option>
</x-ui::select>
```

#### Input Error Component (`resources/views/components/input-error.blade.php`)

**Styling:**
- Text: `text-sm text-red-600` - **Uses hardcoded color instead of `text-danger`**
- Spacing: `space-y-1`

**Usage:**
```blade
<x-input-error :messages="$errors->get('email')" />
```

### Component Gaps

- **Button**: Missing `active:` states
- **Alert**: Uses hardcoded colors instead of design system
- **Badge**: Uses hardcoded colors for secondary and info variants
- **Input Error**: Uses hardcoded `text-red-600` instead of `text-danger`

---

## Interactive States

### Current State

#### Default State
- Base styling applied to all interactive elements
- No special styling required

#### Hover State
- **Buttons**: `hover:bg-primary/90`, `hover:bg-background/subtle`, etc.
- **Links**: `hover:underline` (some instances)
- **Table Rows**: `hover:bg-background/subtle` (some instances)
- **Cards**: No hover state defined

#### Focus State
- **Buttons**: `focus:outline-none focus:ring-2 focus:ring-ring`
- **Inputs**: `focus:ring-2 focus:ring-ring focus:border-ring`
- **Selects**: `focus:ring-2 focus:ring-primary focus:border-primary`
- **Links**: Inconsistent - some have focus states, others don't

#### Active State
- **Buttons**: **Missing** - no `active:` states defined
- **Links**: **Missing** - no active states
- **Other Elements**: **Missing**

#### Disabled State
- **Buttons**: `disabled:opacity-50 disabled:pointer-events-none`
- **Inputs**: Standard HTML disabled attribute (styling may vary)
- **Selects**: Standard HTML disabled attribute

### Current Gaps

- Missing `active:` states on buttons
- Inconsistent focus states across components
- Missing `focus-visible:` for keyboard navigation (better UX for mouse vs keyboard)
- Some interactive elements lack proper focus indicators
- Links don't have consistent hover/focus states

---

## Layout Standards

### Current State

#### Page Container

**Admin Layout** (`resources/views/components/admin/layouts/app.blade.php`):
- Container: `max-w-7xl mx-auto px-4 lg:px-8`
- Vertical Padding: `py-8`
- Max Width: 80rem (1280px)
- Horizontal Padding: 1rem (16px) on mobile, 2rem (32px) on large screens

**Pattern:**
```blade
<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        {{ $slot }}
    </div>
</div>
```

#### Card Padding

- Standard: `p-6` (24px) - most common
- Some cards: `p-4` (16px) - inconsistent

#### Section Spacing

- Between major sections: `mb-6` (24px)
- Some variations: `mb-4` (16px), `py-10` (40px), `py-12` (48px) - inconsistent

#### Grid Patterns

**Responsive Grid:**
- Mobile: `grid-cols-1` (single column)
- Tablet: `md:grid-cols-2` (two columns)
- Desktop: `lg:grid-cols-3` or `lg:grid-cols-4` (three or four columns)
- Gap: `gap-4` (16px) or `gap-6` (24px) - inconsistent

**Example:**
```blade
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <!-- Items -->
</div>
```

#### Alignment Patterns

- **Text**: Left-aligned by default
- **Buttons in Headers**: Right-aligned with `justify-between` on parent
- **Forms**: Left-aligned labels and inputs
- **Tables**: Left-aligned content, right-aligned numbers
- **Modals**: Centered (when implemented)

### Current Inconsistencies

- Inconsistent card padding
- Inconsistent section spacing
- Inconsistent grid gaps
- No documented alignment standards

---

## Accessibility Guidelines

### Current State

#### ARIA Labels

- **Menubar Components**: Uses `role="menubar"` and `role="menuitem"` - **Good**
- **Icon-only Buttons/Links**: Core actions now use `aria-label` (invoices, students, therapists, SSAs, therapist/admin billing, schedule actions).
- **Form Fields**: In progress – some helper texts still need `aria-describedby`.
- **Dynamic Content**: Session logs list exposes a `role="status" aria-live="polite"` region for DataTables updates.

#### Keyboard Navigation

- **Focus States**: Present on buttons and inputs, but inconsistent
- **Tab Order**: Natural DOM order (generally good)
- **Focus Visible**: **Missing** - no distinction between mouse and keyboard focus
- **Keyboard Shortcuts**: Not implemented

#### Color Contrast

- **Not Verified**: No documented contrast ratios
- **Text on Backgrounds**: Uses design system colors but contrast not verified
- **Error States**: Color-only indicators (should have icons or text)

#### Semantic HTML

- **Forms**: Uses proper `<form>`, `<input>`, `<label>` elements - **Good**
- **Tables**: Uses proper `<table>`, `<thead>`, `<tbody>` - **Good**
- **Headings**: Uses proper `<h1>`, `<h2>`, `<h3>` hierarchy - **Good**
- **Navigation**: Uses `<nav>` elements - **Good**
- **Buttons vs Links**: Generally correct, but some links styled as buttons

#### Form Labels

- **Component**: Uses `x-input-label` component - **Good**
- **Association**: Labels properly associated with inputs via `for` attribute - **Good**
- **Error Messages**: Uses `x-input-error` component - **Good**

### Current Gaps

- Remaining icon-only controls should adopt the same `aria-label` pattern when introduced.
- Some helper texts still lack `aria-describedby` references.
- Additional `aria-live` regions may be needed for other dynamic lists/modals.
- `focus-visible:` should be added to any new custom interactive elements.
- Color contrast should be verified when adding new brand colors or backgrounds.

---

## Empty & Loading States

### Current State

#### Empty States

**Component: `x-ui::empty-state`**

- Location: `resources/views/components/ui/empty-state.blade.php`
- Responsibilities:
  - Standardize empty state layout, spacing, and typography
  - Provide optional description text and primary action
  - Allow optional icon slot

**Props:**
- `title` (string, required): Primary empty state message
- `description` (string|null): Optional supporting text
- `actionLabel` (string|null): Optional button label
- `actionHref` (string|null): Optional button URL (used with `actionLabel`)

**Markup:**
```blade
<x-ui::empty-state
    title="No students found."
    description="Try adjusting your filters or add a new student to get started."
    action-label="Add Student"
    :action-href="route('admin.students.create')">
    <x-slot:icon>
        <svg class="mx-auto h-12 w-12 text-foreground/40" ...>
            <!-- Icon -->
        </svg>
    </x-slot:icon>
</x-ui::empty-state>
```

**Current Usage:**
- Used in: students list, therapists list, SSAs list, session logs list, invoices show (no line items), therapist/admin billing lists, activity logs.
- Spacing: `py-12` by default

#### Loading States

**SweetAlert2 Loading:**
- Implemented via `showLoading()` function in `resources/js/common/sweetalert.js`
- Used for async operations (AJAX calls, form submissions)
- Pattern: Show loading dialog, perform operation, close dialog

**Loading States**

**SweetAlert2 Loading:**
- Implemented via `showLoading()` function in `resources/js/common/sweetalert.js`
- Used for async operations (AJAX calls, long-running tasks)

**Inline Button Loading Pattern:**

- Used on key form submissions (e.g., therapist session logs form, invoice send button).
- Pattern:
  - Disable button on submit
  - Swap label with spinner + \"Saving...\" / \"Sending...\"

```blade
<button type="submit"
    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    x-data="{ loading: false }"
    x-on:click="loading = true"
    x-bind:disabled="loading">
    <span x-show="!loading">Save</span>
    <span x-show="loading" class="inline-flex items-center gap-2">
        <svg class="animate-spin h-4 w-4 text-primary-foreground" ...></svg>
        Saving...
    </span>
</button>
```

**Table / List Loading (Planned):**
- Prefer SweetAlert2 loading or lightweight skeleton rows for heavy async tables.

---

## Typography Standards

### Standard Typography Scale

The following typography scale should be used consistently across all views:

#### Headings

- **Page Title (H1)**: `text-2xl font-semibold text-foreground`
  - Usage: Main page titles, primary headings
  - Size: 1.5rem (24px)
  - Weight: 600 (semibold)
  - Example: "Invoices", "Students", "Dashboard"

- **Section Title (H2)**: `text-lg font-semibold text-foreground`
  - Usage: Card section headers, major content sections
  - Size: 1.125rem (18px)
  - Weight: 600 (semibold)
  - Example: "Invoice Details", "Line Items", "Student Information"

- **Subsection Title (H3)**: `text-sm font-medium text-foreground/70`
  - Usage: Form section labels, subsection headers, field group labels
  - Size: 0.875rem (14px)
  - Weight: 500 (medium)
  - Example: "Bill To", "From", "Basic Information"

#### Body Text

- **Body Text**: `text-sm text-foreground`
  - Usage: Most content, paragraphs, table cells, form field values
  - Size: 0.875rem (14px)
  - Weight: 400 (normal)
  - Example: Regular content, descriptions

- **Large Body Text**: `text-base text-foreground`
  - Usage: Important content, emphasized paragraphs (use sparingly)
  - Size: 1rem (16px)
  - Weight: 400 (normal)
  - Example: Important notices, key information

#### Supporting Text

- **Muted Text**: `text-sm text-foreground/60`
  - Usage: Secondary information, descriptions, helper text
  - Size: 0.875rem (14px)
  - Weight: 400 (normal)
  - Example: "Here's your NOVA command center overview", date labels

- **Label**: `text-xs font-medium text-foreground/70`
  - Usage: Form field labels, small labels
  - Size: 0.75rem (12px)
  - Weight: 500 (medium)
  - Example: "School", "Status", "Date From"

- **Caption**: `text-xs text-foreground/60`
  - Usage: Captions, fine print, timestamps
  - Size: 0.75rem (12px)
  - Weight: 400 (normal)
  - Example: "Auto-generated number is shown. You can edit if needed."

#### Font Weights

- **Normal (400)**: Default for body text
- **Medium (500)**: Labels, subsection titles, emphasis
- **Semibold (600)**: Headings (H1, H2)

### Typography Usage Rules

**DO:**
- Use the standard scale consistently
- Use `text-foreground` for primary text
- Use `text-foreground/60` or `text-foreground/70` for muted text
- Use `font-semibold` for headings
- Use `font-medium` for labels and emphasis

**DON'T:**
- Mix font sizes without reason
- Use arbitrary font sizes (e.g., `text-[13px]`)
- Use `font-bold` (700) - use `font-semibold` (600) instead
- Use hardcoded colors for text - use foreground color system

---

## Spacing Standards

### Standard Spacing Scale

The following spacing scale should be used consistently:

#### Spacing Levels

- **Tight (2)**: `gap-2`, `space-y-2`, `p-2`, `m-2` (8px)
  - Usage: Tightly related elements, icon + text pairs, compact layouts

- **Normal (4)**: `gap-4`, `space-y-4`, `p-4`, `m-4` (16px)
  - Usage: Standard spacing between elements, form field groups, grid gaps

- **Loose (6)**: `gap-6`, `space-y-6`, `p-6`, `m-6` (24px)
  - Usage: Section separation, card padding, major element spacing

- **Extra Loose (8)**: `gap-8`, `space-y-8`, `p-8`, `m-8` (32px)
  - Usage: Major page sections, large content blocks

#### Standard Spacing Patterns

**Card Padding:**
- Standard: `p-6` (24px)
- Use consistently for all cards

**Section Spacing:**
- Between major sections: `mb-6` (24px)
- Between related sections: `mb-4` (16px)

**Form Field Spacing:**
- Between form fields: `space-y-4` or `gap-4` (16px)
- Between form groups: `space-y-6` (24px)

**Button Padding:**
- Standard: `px-4 py-2` (16px horizontal, 8px vertical)
- Small: `px-3 py-2` (12px horizontal, 8px vertical)
- Large: `px-5 py-2` (20px horizontal, 8px vertical)

**Grid Gaps:**
- Standard: `gap-4` (16px)
- Use consistently for all grids

**Empty State Spacing:**
- Standard: `py-12` (48px vertical)
- Use consistently for all empty states

### Spacing Usage Rules

**DO:**
- Use the standard spacing scale (2, 4, 6, 8)
- Use `p-6` for card padding
- Use `mb-6` for section spacing
- Use `gap-4` for grid gaps
- Use `space-y-4` for form field spacing

**DON'T:**
- Use arbitrary spacing values (e.g., `p-5`, `mb-10`, `py-12` for cards)
- Mix spacing levels without reason
- Use inconsistent spacing for similar elements

---

## Component Standards

### Button Standards

**Required States:**
- Default: Base variant styling
- Hover: `hover:bg-{variant}/90` or appropriate hover state
- Focus: `focus:outline-none focus:ring-2 focus:ring-ring`
- Active: `active:bg-{variant}/80` or appropriate active state
- Disabled: `disabled:opacity-50 disabled:pointer-events-none`

**Variants:**
- `primary`: For main actions
- `secondary`: For secondary actions
- `ghost`: For tertiary actions
- `success`: For positive actions
- `danger`: For destructive actions

**Sizes:**
- `sm`: Small buttons (32px height)
- `md`: Standard buttons (36px height) - default
- `lg`: Large buttons (40px height)

### Card Standards

**Required Styling:**
- Background: `bg-white`
- Border: `border border-border`
- Border Radius: `rounded-lg`
- Shadow: `shadow-sm`
- Padding: `p-6` (standard)

**Usage:**
- Use cards to group related content
- Always use `p-6` for padding
- Use cards for forms, content sections, data displays

### Form Field Standards

**Required Elements:**
- Label: `x-input-label` component
- Input: `x-ui::input` or appropriate input component
- Error: `x-input-error` component
- Spacing: `space-y-4` or `gap-4` between fields

**Error Display:**
- Show errors below the field using `x-input-error`
- Use `text-danger` for error text (not hardcoded red)
- Show errors contextually near their source

### Table Standards

**Required Styling:**
- Header: `bg-background/subtle text-foreground`
- Rows: Hover state `hover:bg-background/subtle`
- Borders: `border border-border`
- Spacing: Consistent padding in cells

**Structure:**
- Use semantic HTML (`<table>`, `<thead>`, `<tbody>`)
- Use proper header cells (`<th>`)
- Use proper data cells (`<td>`)

### Component Usage Rules

**DO:**
- Use design system components
- Follow component variant patterns
- Use consistent spacing
- Include all required states

**DON'T:**
- Create ad-hoc components without documenting
- Use hardcoded colors in components
- Skip required states
- Mix component patterns

---

## Interactive State Standards

### Required States for All Interactive Elements

#### Buttons

**Complete State Pattern:**
```
hover:bg-primary/90 
focus:outline-none focus:ring-2 focus:ring-ring 
active:bg-primary/80 
disabled:opacity-50 disabled:pointer-events-none
```

**Implementation:**
- Default: Base variant styling
- Hover: Darken background by 10% opacity (`/90`)
- Focus: Remove outline, add 2px ring with primary color
- Active: Darken background further (`/80`)
- Disabled: Reduce opacity to 50%, disable pointer events

#### Inputs

**Complete State Pattern:**
```
focus:ring-2 focus:ring-ring focus:border-ring
```

**Implementation:**
- Default: Base input styling
- Focus: Add 2px ring and change border color to ring color
- Disabled: Standard HTML disabled attribute

#### Links

**Complete State Pattern:**
```
hover:underline 
focus:outline-none focus:ring-2 focus:ring-ring
```

**Implementation:**
- Default: Base link styling (usually primary color)
- Hover: Add underline
- Focus: Remove outline, add 2px ring

#### Table Rows

**Complete State Pattern:**
```
hover:bg-background/subtle
```

**Implementation:**
- Default: Transparent background
- Hover: Light background color for better UX

### Keyboard Navigation

**Focus Visible:**
- Use `focus-visible:` for better keyboard navigation UX
- Distinguishes between mouse and keyboard focus
- Example: `focus-visible:ring-2 focus-visible:ring-ring`

### Interactive State Rules

**DO:**
- Include all required states (hover, focus, active, disabled)
- Use consistent state patterns across components
- Use `focus-visible:` for keyboard navigation
- Test keyboard-only navigation

**DON'T:**
- Skip required states
- Use inconsistent state patterns
- Rely on color alone for state indication
- Ignore keyboard navigation

---

## Design Quality Checklist

Before considering any UI implementation complete, verify the following:

### Visual Foundation
- [ ] Follows established color palette (no ad-hoc colors)
- [ ] Uses typography scale correctly
- [ ] Uses spacing scale consistently

### Visual Hierarchy
- [ ] Has clear visual hierarchy with one primary action per view
- [ ] Headings follow typography standards
- [ ] Text hierarchy is clear (primary, secondary, muted)

### Component Behavior
- [ ] Includes all required states: default, hover, focus, active, disabled
- [ ] Loading states are implemented (for async operations)
- [ ] Empty states are implemented (for empty data)
- [ ] Error states are implemented (for validation errors)
- [ ] Success states are implemented (for successful actions)

### Layout
- [ ] Related content is properly grouped (cards, sections)
- [ ] Spacing follows standards (card padding, section spacing)
- [ ] Alignment is consistent (left-aligned text, proper button alignment)

### Responsiveness
- [ ] Works on mobile viewport (tested)
- [ ] Works on tablet viewport (tested)
- [ ] Works on desktop viewport (tested)
- [ ] Grid layouts are responsive

### Accessibility
- [ ] Passes keyboard-only navigation test
- [ ] ARIA labels are present where needed
- [ ] Color contrast is sufficient (verified)
- [ ] Semantic HTML is used correctly
- [ ] Focus states are visible and clear

### Consistency
- [ ] Matches established patterns from reference pages
- [ ] Uses design system components
- [ ] Follows spacing and typography standards

---

## Reference

### Key Files

- **Tailwind Config**: `tailwind.config.js`
- **Design Principles**: `.cursor/Design Principles.md`
- **Frontend Standards**: `docs/FRONTEND_STANDARDS.md`
- **CSS Guidelines**: `resources/css/README.md`

### Component Locations

- **UI Components**: `resources/views/components/ui/`
- **Layout Components**: `resources/views/components/admin/layouts/`
- **Form Components**: `resources/views/components/`

---

## UI Component Namespace Standards

### Component Naming Convention

All design system UI components use the `x-ui::*` namespace prefix. This namespace maps to components in `resources/views/components/ui/`.

**ALWAYS use the `x-ui::` prefix** for design system components:

```blade
{{-- Correct --}}
<x-ui::input type="text" name="email" />
<x-ui::button variant="primary">Submit</x-ui::button>
<x-ui::card class="p-6">Content</x-ui::card>
<x-ui::checkbox name="remember" label="Remember me" />
<x-ui::select name="school_id">...</x-ui::select>
<x-ui::empty-state title="No data" />

{{-- Incorrect (legacy patterns - do not use) --}}
<x-text-input type="text" name="email" />
<x-primary-button>Submit</x-primary-button>
<x-ui-input type="text" name="email" />
<x-ui-checkbox name="remember" />
```

### Available UI Components

#### Form Controls
| Component | Usage |
|-----------|-------|
| `x-ui::input` | Text, email, number, date, time, password inputs |
| `x-ui::select` | Styled select with optional search (supports Select2) |
| `x-ui::checkbox` | Inline checkbox with optional label |
| `x-ui::checkbox-row` | Boolean setting row (label + subtext + tooltip) for settings panels |
| `x-ui::file-input` | File upload control |
| `x-ui::status-toggle` | Toggle for active/inactive states |

#### Buttons & Feedback
| Component | Usage |
|-----------|-------|
| `x-ui::button` | Primary, secondary, ghost, success, danger buttons |
| `x-ui::loading-button` | Button with loading state |
| `x-ui::alert` | Alert banners for success/error/info |
| `x-ui::badge` | Status labels and chips |

#### Layout & Page Structure
| Component | Usage |
|-----------|-------|
| `x-ui::card` | Container for forms, sections, widgets |
| `x-ui::show-header` | Standard header for show/detail pages |
| `x-ui::tabs` | Tabbed navigation within a page |
| `x-ui::metric-grid` | Grid layout for key metrics |

#### Tables & Data Display
| Component | Usage |
|-----------|-------|
| `x-ui::table` | Simple table wrapper |
| `x-ui::datatable` | DataTables-enabled table wrapper |
| `x-ui::session-log-table` | Specialized session log table |
| `x-ui::empty-state` | Empty state with optional action |
| `x-ui::table-loading` | Skeleton loading for tables |
| `x-ui::skeleton` | Generic skeleton loader |

#### Menus & Navigation
| Component | Usage |
|-----------|-------|
| `x-ui::menubar` | Structured menubar container |
| `x-ui::menubar-menu` | Menu within menubar |
| `x-ui::menubar-item` | Item within menu |
| `x-ui::menubar-separator` | Separator between items |

### Component Props Reference

#### x-ui::input
```blade
<x-ui::input
    type="text"           {{-- text, email, number, date, time, password --}}
    name="field_name"
    id="field_id"
    value="initial value"
    placeholder="Enter value"
    disabled               {{-- Optional: disable input --}}
    required               {{-- Optional: make required --}}
    class="mt-1 block w-full"
/>
```

#### x-ui::button
```blade
<x-ui::button
    variant="primary"     {{-- primary, secondary, ghost, success, danger --}}
    size="md"             {{-- sm, md, lg --}}
    type="button"         {{-- button, submit, reset --}}
    disabled              {{-- Optional: disable button --}}
>
    Button Text
</x-ui::button>
```

#### x-ui::select
```blade
<x-ui::select
    name="field_name"
    id="field_id"
    :searchable="true"    {{-- Enable Select2 search --}}
    multiple              {{-- Allow multiple selections --}}
    class="mt-1"
>
    <option value="">Select...</option>
    <option value="1">Option 1</option>
</x-ui::select>
```

#### x-ui::checkbox
```blade
<x-ui::checkbox
    name="field_name"
    id="field_id"
    value="1"
    label="Checkbox label"
    disabled              {{-- Optional: disable checkbox --}}
    @checked($condition)
/>
```

Use for inline checkboxes inside tables, filter bars, or single-line forms. For settings panels with multiple toggles, use `x-ui::checkbox-row` instead.

#### x-ui::checkbox-row

The standard pattern for **boolean settings** in a form — used wherever multiple toggle-style options sit together (settings panels, characteristic sections, preference grids). Renders a checkbox with a bold label, an optional info tooltip, and an optional one-line subtext. Replaces the bordered-card "toggle card" pattern, which wastes vertical space and forces inline help text to wrap.

```blade
<x-ui::checkbox-row
    name="allow_weekend_scheduling"
    label="Allow weekend scheduling"
    subtext="Saturdays and Sundays available"
    tooltip="When enabled, therapists can schedule sessions on Saturdays and Sundays."
    :checked="old('allow_weekend_scheduling', $school->allow_weekend_scheduling ?? false)"
/>
```

**Props**

| Prop | Required | Notes |
|---|---|---|
| `name` | yes | Form field name. Also used as the `id` if `id` is not passed. A `<input type="hidden" value="0">` is rendered automatically so unchecked submits as `0`. |
| `id` | no | Defaults to `name`. |
| `label` | yes | Short, action-oriented. **2–4 words.** Sentence case. Use a noun or verb phrase (`"Private student"`, `"Allow weekend scheduling"`), not a question. |
| `subtext` | no | One line, **≤6 words**. Plain-language clarification of what enabling the toggle means in practice (`"Family record, not a district school"`). Skip if the label is self-explanatory. |
| `tooltip` | no | The full, long-form explanation — rendered as an info icon next to the label. This is where business rules, side-effects, and notification details go. **Do not** repeat the subtext here. |
| `checked` | no | Boolean. Default `false`. |
| `disabled` | no | Boolean. Default `false`. |
| `value` | no | Submitted value when checked. Default `'1'`. |
| `errorBag` | no | Validation key. Defaults to `name`. |

**Layout**

Place rows in a responsive grid. Pick the column count that lets every row fit on one line without label wrap — typically **2 columns for 2 toggles, 4 columns for 3–4 toggles**. Always stack to 1 column on mobile.

```blade
{{-- 3–4 toggles --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-6">
    <x-ui::checkbox-row ... />
    <x-ui::checkbox-row ... />
    <x-ui::checkbox-row ... />
    <x-ui::checkbox-row ... />
</div>

{{-- 2 toggles --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
    <x-ui::checkbox-row ... />
    <x-ui::checkbox-row ... />
</div>
```

**Copy rules**

- **Label is the toggle, subtext is the consequence.** Label = what the setting *is*; subtext = what changes when it's on.
- **No long sentences inline.** Anything longer than 6 words goes in the `tooltip`, not the `subtext`.
- **No "when enabled, …" preamble in labels or subtext.** That phrasing belongs in the tooltip.
- **No question marks in labels.** `"Private student"` not `"Private student?"`.
- **Sentence case**, not Title Case.

**When NOT to use**

- For a single, primary checkbox in a flow (e.g. "I agree to the terms") — use `x-ui::checkbox` with a label.
- For tri-state filters or multi-select option lists — use a multi-select or chip group.
- For mutually exclusive options — use radios.

#### x-ui::empty-state
```blade
<x-ui::empty-state
    title="No items found"
    description="Optional description text"
    action-label="Add Item"
    :action-href="route('items.create')"
>
    <x-slot:icon>
        {{-- Optional icon --}}
    </x-slot:icon>
</x-ui::empty-state>
```

### Migration Notes

The following legacy component patterns have been standardized:

| Legacy Pattern | Replaced By |
|----------------|-------------|
| `x-text-input` | `x-ui::input` |
| `x-primary-button` | `x-ui::button variant="primary"` |
| `x-secondary-button` | `x-ui::button variant="secondary"` |
| `x-ui-input` | `x-ui::input` |
| `x-ui-checkbox` | `x-ui::checkbox` |
| `x-ui-file-input` | `x-ui::file-input` |

All views have been migrated to use the standardized `x-ui::*` namespace.

### JavaScript Utilities

- **SweetAlert2**: `resources/js/common/sweetalert.js`
- **DataTables**: `resources/js/common/datatables.js`
- **Chart Colors**: `resources/js/common/chart-colors.js`

---

## Notes

- This documentation should be updated as the design system evolves
- All new components should be documented here
- Style fixes should reference this documentation
- When inconsistencies are found, document them here and plan fixes
- This serves as the single source of truth for the NOVA design system

