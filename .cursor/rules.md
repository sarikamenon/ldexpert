# Cursor Coding Rules for Bird (Laravel)

- Monolith only: no public API controllers. Use Blade pages with Form Requests and jQuery AJAX where asynchronous behavior is needed.
- Always use DTOs for input transport between layers.
- Always use Form Request classes for validation. Controllers MUST type-hint Request objects from `app/Http/Requests/**`.
- Controllers must delegate to Services; Services use Repositories.
- Prefer Eloquent; raw queries only with justification.
- Tests are mandatory for new logic:
  - Unit tests for DTOs/Services/Repositories
  - Feature tests for routes/commands
- Keep CSS and JS in separate files. Use Tailwind for styles.
- Use jQuery for DOM/AJAX interactivity; avoid vanilla JS for features.
- Create Blade UI components in `resources/views/components/ui` and reuse them.
- No public registration routes; users created via command or privileged UI.
- Roles: `admin`, `therapist`, `student`, `parent`. Protect routes with `role` middleware.
- Follow PSR-12; run `make qa` before commits.

Project-enforced conventions

- Always run commands via Docker:
  - Use `docker compose exec -T app bash -lc 'cd app && <command>'` or Makefile targets (e.g., `make migrate`, `make qa`). Never run host PHP/Node directly.

Quality gates

- Every development task must include tests and follow these rules by default, even if work will be refined later.
