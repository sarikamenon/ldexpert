---
name: test-writer
description: Write tests for the NOVA Laravel project using Pest PHP. Covers unit tests for DTOs/Services/Repositories, feature tests for HTTP routes, and Dusk browser tests for UI. Use when the user asks to write tests, add test coverage, or when implementing a new feature that needs tests. Triggers on "write tests", "add tests for", "test coverage", "need tests", or after implementing new features.
---

# Test Writer for NOVA

Write comprehensive tests using Pest 3.x following NOVA conventions.

## Test Types

### Unit Tests (`tests/Unit/`)
For DTOs, Services, Repositories — isolated logic.

```php
declare(strict_types=1);

use App\DTOs\StudentFilterDTO;

it('creates filter DTO from array', function (): void {
    $dto = StudentFilterDTO::fromArray([
        'school_id' => 1,
        'status' => 'active',
    ]);

    expect($dto->schoolId)->toBe(1);
    expect($dto->status)->toBe('active');
});

it('converts filter DTO to array', function (): void {
    $dto = StudentFilterDTO::fromArray(['school_id' => 1]);
    $array = $dto->toArray();

    expect($array)->toHaveKey('school_id', 1);
});
```

### Feature Tests (`tests/Feature/`)
For HTTP routes — full request/response cycle.

```php
declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

it('allows admin to view student list', function (): void {
    $admin = User::factory()->create(['role' => Role::ADMIN]);

    $this->actingAs($admin)
        ->get(route('admin.students.index'))
        ->assertOk()
        ->assertViewIs('admin.students.index');
});

it('denies therapist access to admin students', function (): void {
    $therapist = User::factory()->create(['role' => Role::THERAPIST]);

    $this->actingAs($therapist)
        ->get(route('admin.students.index'))
        ->assertForbidden();
});

it('validates required fields on student create', function (): void {
    $admin = User::factory()->create(['role' => Role::ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.students.store'), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
});
```

### Dusk Tests (`tests/Browser/`)
For UI interactions — forms, buttons, JavaScript behavior.

```php
declare(strict_types=1);

use App\Models\User;
use Laravel\Dusk\Browser;

it('can create a student via form', function (): void {
    $this->browse(function (Browser $browser): void {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $browser->loginAs($admin)
            ->visit(route('admin.students.create'))
            ->type('first_name', 'John')
            ->type('last_name', 'Doe')
            ->type('email', 'john@example.com')
            ->press('Create Student')
            ->assertSee('Student created successfully');
    });
});
```

## Test Checklist for New Features

- [ ] Unit: DTO `fromArray()` and `toArray()` with valid data
- [ ] Unit: DTO with missing/invalid data
- [ ] Unit: Service methods with mocked repository
- [ ] Feature: Route accessible by authorized role
- [ ] Feature: Route blocked for unauthorized roles
- [ ] Feature: Validation errors for missing required fields
- [ ] Feature: Successful create/update with valid data
- [ ] Feature: Soft delete behavior
- [ ] Dusk: Form submission with valid data (if UI exists)
- [ ] Dusk: Validation error display in browser

## Running Tests

```bash
make test              # Run all Pest tests
make test-unit         # Unit tests only
make test-feature      # Feature tests only
make dusk              # Browser tests (headless)
```

## Conventions

- Use `declare(strict_types=1)` in all test files
- Use Pest's `it()` syntax, not PHPUnit `test` methods
- Use factories for test data — never create records manually
- Test both success AND failure scenarios
- Test authorization rules (who can and cannot access)
- Use `RefreshDatabase` trait (already in `TestCase`)
