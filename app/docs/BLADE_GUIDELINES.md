# Blade View Guidelines

The single source of truth for how Blade views must be written in this project. CLAUDE.md and AGENTS.md point here — they do not duplicate these rules.

## Core principle

**Blade prints strings. PHP shapes data. JavaScript handles behavior.** A `.blade.php` file should be (almost) pure HTML with Blade directives. If you find yourself writing `foreach`-with-logic, computing arrays, or pasting `<script>` blocks inside a view — stop and move it out.

---

## 1. No `@php` blocks for data shaping

`@php` blocks in views are an anti-pattern. They put presentation logic in the wrong layer, hide work from PHPStan, and make views hard to test.

**Banned**

```blade
@php
    $isEdit = isset($student);
    $profile = $student->studentProfile ?? null;
    $privateFamilyContacts = $schools
        ->where('is_private_student', true)
        ->mapWithKeys(fn ($s) => [...]);
@endphp
```

**Required** — compute in the controller, pass as view variables:

```php
// Controller
return view('admin.students.edit', [
    'student' => $student,
    'isEdit' => true,
    'profile' => $student->studentProfile,
    'privateFamilyContacts' => $this->buildPrivateFamilyContacts($schools),
] + $this->referenceData());
```

```blade
{{-- View --}}
<form action="{{ $isEdit ? route('admin.students.update', $student) : route('admin.students.store') }}">
```

**The only acceptable `@php` use**: a one-line, view-local convenience that doesn't shape data — e.g. combining a few conditional CSS classes that only matter inside that view. If it could be useful to another view, it goes in the controller (or a view-model / view-composer).

---

## 2. No `<script>` blocks in `.blade.php` — including data islands

**Banned — executable JS:**

```blade
<script>
    document.getElementById('foo').addEventListener('click', () => { ... });
</script>
```

**Also banned — JSON data islands:**

```blade
<script type="application/json" id="private-family-contact-data">
    @json($privateFamilyContacts)
</script>
```

Data islands look "safe" because they're inert, but they put server-rendered config in the view, get duplicated across pages, and bypass the `resources/js/pages/` discipline.

**Required pattern — `data-*` attributes on a hidden element:**

```blade
{{-- View --}}
<div id="students-form-data"
    data-private-student-ids="{{ json_encode($privateStudentIds) }}"
    data-private-family-contacts="{{ json_encode($privateFamilyContacts) }}"
    hidden></div>
```

```javascript
// resources/js/pages/admin-students-form.js
const $formData = $('#students-form-data');
const privateStudentIds = $formData.data('private-student-ids') ?? [];
const familyContacts = $formData.data('private-family-contacts') ?? {};
```

jQuery's `.data()` auto-parses JSON. Vanilla equivalent: `JSON.parse(el.dataset.privateStudentIds)`.

**All page-specific JS must:**
1. Live in `resources/js/pages/<name>.js`.
2. Be registered in `vite.config.js`.
3. Be loaded via `<x-slot name="scripts">@vite('resources/js/pages/<name>.js')</x-slot>` (or `@push('styles') @vite(...) @endpush` if that's the page's pattern) in the parent view.

"It's only a few lines" is not an exception.

---

## 3. Color tokens — canonical list

NEVER use raw Tailwind palette (`gray-*`, `blue-*`, `red-*`, `green-*`, `yellow-*`, `indigo-*`) or hex literals (`#e5e7eb`) in Blade or JS. The only exception is a dynamic user-supplied color (e.g. a color picker value) going into a `style=` attribute — and it must be escaped with `e()`.

### Form controls
| Use case | Token |
|---|---|
| Border on inputs, textareas, checkboxes, radios | `border-input` |
| Border on layout/cards/dividers | `border-border` |
| Focus ring | `focus:ring-ring` |
| Focus border (on inputs that show a colored border) | `focus:border-primary` |

### Backgrounds
| Use case | Token |
|---|---|
| Page / card background | `bg-background` |
| Subdued panel / hover row | `bg-muted` |
| Primary action | `bg-primary` |
| Destructive action | `bg-danger` |

### Text
| Use case | Token |
|---|---|
| Primary text | `text-foreground` |
| Secondary / labels | `text-foreground/70` |
| Help text / muted | `text-foreground/60` |
| Placeholder / disabled | `text-foreground/40` |
| Destructive | `text-danger` |

### Common mistakes mapped to the fix
| Wrong | Right |
|---|---|
| `border-gray-300` | `border-input` (form control) or `border-border` (layout) |
| `focus:ring-primary` on checkbox | `focus:ring-ring` |
| `text-gray-500` | `text-foreground/60` |
| `bg-gray-50` | `bg-muted` |
| `text-red-600` | `text-danger` |
| `style="border: 1px solid #e5e7eb"` | use a class with `border-input` / `border-border` |

When in doubt, read the matching `resources/views/components/ui/` component — it uses the right tokens already.

---

## 4. Pre-format data in controllers, not in Blade

Blade prints strings. Controllers and services prepare those strings.

**Banned in views:**
- `$log->sent_at->format(...)` — pre-format in the controller
- `number_format($x->amount, 2)` — attach a `formatted_amount` accessor or controller-side property
- `match (...) { ... }` for label lookups — pass a label

**Required pattern:**

```php
// Controller / service
$log->sent_at_formatted = $log->sent_at?->setTimezone($viewerTz)->format(config('display.datetime'));
```

```blade
{{ $log->sent_at_formatted }}
```

Annotate transient properties with `@property string|null $sent_at_formatted` on the model so PHPStan is happy.

### Date and time display

- **12-hour time** via `config('display.time')` → `g:i A` (e.g. `9:30 AM`). Always use this for user-visible times.
- **Combined datetime** via `config('display.datetime')` → `M d, Y g:i A`.
- The only exception is `<input type="time">` `value=""` — HTML spec requires `H:i`. Keep those literal.
- Never hardcode `H:i` in display output.

---

## 5. Form structure (MANDATORY)

Every form input must follow this order: **Label → Help Text → Input → Error**.

```blade
<div>
    <x-input-label for="email" value="Email *" />
    <p class="mt-1 text-xs text-foreground/60" id="email_help">
        Email for notifications (can be shared with siblings).
    </p>
    <x-ui::input id="email" name="email" type="email"
        class="mt-1 block w-full"
        :value="old('email', $user?->email)"
        aria-describedby="email_help" />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>
```

- Help text class: `mt-1 text-xs text-foreground/60`.
- Help text must have an `id` and the input must reference it via `aria-describedby` (a11y).
- Help text is 1-2 sentences. State units / ranges / timezone where relevant.
- Help text and label must describe the **same thing** — re-read them together before committing.

---

## 6. Use design-system components, never hand-rolled

- Blade UI components live in `resources/views/components/ui/`. Use them. Don't re-implement inputs, selects, buttons, cards.
- **Always wrap forms and page sections in `<x-ui::card class="p-6 space-y-4">`** — never bare `<div>`s for sections.
- Page-specific reusable widgets go in `resources/views/components/<page>/`.
- Documented patterns live in `app/docs/DESIGN_SYSTEM.md`. New patterns are documented there **before** being implemented.

---

## 7. UI/UX standards (typography, spacing, a11y, states)

These rules apply to every UI surface. See `app/docs/DESIGN_SYSTEM.md` for the full reference.

### Typography scale
| Use | Classes |
|---|---|
| H1 | `text-2xl font-semibold text-foreground` |
| H2 | `text-lg` |
| H3 | `text-sm font-medium text-foreground/70` |
| Body | `text-sm` |
| Labels | `text-xs font-medium text-foreground/70` |

### Spacing
- Standard scale: `2`, `4`, `6`, `8`.
- Card padding: `p-6`.
- Section spacing: `mb-6`.

### Interactive states (MANDATORY)
Every interactive element (buttons, inputs, links, rows, etc.) must define:
- `hover:` state
- `focus:` state
- `focus-visible:` state (keyboard-only ring)
- `active:` state (pressed)
- `disabled:` state

If a state isn't visually distinct, fix it before merging.

### Accessibility (MANDATORY)
- All interactive elements must be reachable and operable via keyboard.
- Sufficient color contrast (WCAG AA min — the design tokens are tuned for this; raw palette colors usually aren't).
- Use semantic HTML (`<button>` for buttons, `<a>` for links, `<label>` for labels, `<nav>`, `<main>`, etc.).
- Form inputs reference their help text via `aria-describedby` (see §5).
- Icon-only buttons must have `aria-label` or `<span class="sr-only">`.

### Responsiveness (MANDATORY)
- Every page must work on mobile, tablet, and desktop.
- Default to mobile-first Tailwind breakpoints (`sm:`, `md:`, `lg:`).
- Tables: use horizontal scroll or stack rows on small screens.

### Empty states
- Use `<x-ui::empty-state>` for "no records" / "nothing to show here" surfaces. Never hand-roll empty-state UI.

### Destructive actions
- Any destructive action (delete, deactivate, bulk-remove) requires explicit confirmation via SweetAlert2. See `app/docs/FRONTEND_STANDARDS.md` for the helper module.

### Document-first
- New UI patterns are documented in `app/docs/DESIGN_SYSTEM.md` **before** being implemented in any view. If it doesn't have a place in the doc yet, add the entry first.

---

## Pre-commit Self-Review Checklist (Blade)

Run through this before marking any view work done:

- [ ] No `@php` blocks shaping data — controller passes view-ready variables
- [ ] No `<script>` blocks of any kind (including `type="application/json"`) — JS lives in `resources/js/pages/`, data passes via `data-*` attributes
- [ ] No `gray-*`, `blue-*`, `red-*`, `green-*`, `yellow-*`, `indigo-*` or hex literals — only design tokens
- [ ] Every form input has Label → Help Text → Input → Error in that order, with `aria-describedby`
- [ ] Help text and label describe the same thing (re-read them together)
- [ ] Sections use `<x-ui::card>`, not bare `<div>`s
- [ ] All user-visible times use `config('display.time')` / `config('display.datetime')`
- [ ] Dates and numbers are pre-formatted in the controller, not in Blade
