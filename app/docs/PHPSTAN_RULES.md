# PHPStan Level 8 Compliance Rules

This project enforces PHPStan Level 8 with Larastan (`checkModelProperties: true`). All new and modified PHP code MUST pass with zero errors. The rules below prevent the most common violations.

## General

- Every PHP file MUST start with `declare(strict_types=1);`.
- Every method MUST have a native return type. If the return type involves generics (Collection, Builder, Paginator, arrays), also add a `@return` PHPDoc tag.
- Every method parameter MUST be typed (native type + PHPDoc for generics).
- Never use bare `array` as a type — always specify value types: `array<string, mixed>`, `array<int, string>`, `array{key: type, ...}`, etc.

## Model Relations

Every Eloquent relation method MUST include full generic annotations:

```php
/** @return HasOne<TherapistProfile, $this> */
public function therapistProfile(): HasOne { ... }

/** @return BelongsTo<User, $this> */
public function student(): BelongsTo { ... }

/** @return HasMany<SessionLog, $this> */
public function sessionLogs(): HasMany { ... }

// BelongsToMany with custom pivot:
/** @return BelongsToMany<Service, $this, SSAService, 'pivot'> */
public function services(): BelongsToMany { ... }

// BelongsToMany with default pivot (no ->using()):
/** @return BelongsToMany<User, $this> */
public function students(): BelongsToMany { ... }

// MorphMany:
/** @return MorphMany<Document, $this> */
public function documents(): MorphMany { ... }
```

## HasFactory Trait

Every model using `HasFactory` MUST have a `@use` annotation:

```php
// With a dedicated factory:
/** @use HasFactory<\Database\Factories\UserFactory> */
use HasFactory;

// Without a dedicated factory:
/** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
use HasFactory;
```

## Eloquent Nullability Rules

- **BelongsTo relations** return `Model|null`. When accessing properties through a BelongsTo, use nullsafe: `$sessionLog->student?->name`.
- **Cast enum properties** are always the enum type (never null, never string) when the column is NOT nullable. Do NOT use `?->` on them: `$model->status->value` (correct), NOT `$model->status?->value`.
- **Nullable Carbon columns** (`end_date`, etc.) may be null. Use nullsafe: `$ssa->end_date?->format('Y-m-d') ?? ''`.
- **Non-nullable Carbon columns** (`start_date`, `created_at`) are always Carbon. Do NOT use `?->`: `$ssa->start_date->format('Y-m-d')`.

## Collections, Builders, and Paginators

Always specify generics on Collection, Builder, and Paginator types:

```php
/** @return Collection<int, SessionLog> */
/** @return Builder<User> */
/** @return LengthAwarePaginator<int, SessionLog> */
/** @param Collection<int, ServiceSupportAgreement> $ssas */
```

**Important**: `LengthAwarePaginator` from `Illuminate\Contracts\Pagination` is NOT iterable. Use `->items()` to get the array for `foreach`:

```php
foreach ($paginator->items() as $item) { ... }
```

## FormRequest Methods

```php
// rules() — MUST have this exact annotation:
/** @return array<string, array<int, mixed>|string> */
public function rules(): array { ... }

// messages() — MUST have this annotation:
/** @return array<string, string> */
public function messages(): array { ... }

// withValidator() — MUST type the parameter:
public function withValidator(\Illuminate\Validation\Validator $validator): void { ... }

// baseRules() in abstract FormRequests:
/** @return array<string, array<int, mixed>|string> */
protected function baseRules(): array { ... }
```

## Model Scopes

Scope methods MUST type both parameter and return:

```php
/**
 * @param Builder<User> $query
 * @return Builder<User>
 */
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}
```

## Repository & Service Methods

- Interface and implementation MUST have matching `@param` and `@return` PHPDoc.
- Never return bare `array` — always specify shape or value types:

```php
/** @param array<string, mixed> $data */
public function create(array $data): Model;

/** @return array{total: int, active: int, inactive: int} */
public function metrics(): array;

/** @return array<string, mixed> */
public function formPayload(): array;
```

## DTO Methods

```php
/** @param array<string, mixed> $data */
public static function fromArray(array $data): self { ... }

/** @return array<string, mixed> */
public function toArray(): array { ... }
```

## Common Pitfalls and Fixes

| Pitfall | Wrong | Correct |
|---------|-------|---------|
| `file_get_contents()` returns `string\|false` | `$content = file_get_contents($path);` | `$content = (string) file_get_contents($path);` |
| `fgetcsv()` returns nullable values | `array_map('trim', $row)` | `array_map(static fn ($v): string => trim((string) $v), $row)` |
| `Model::find()` returns `Model\|null` | `$user = User::find($id);` | `/** @var User $user */ $user = User::findOrFail($id);` |
| `Model::findOrFail()` union type | `$m = Model::findOrFail($id);` | `/** @var User $m */ $m = User::findOrFail($id);` |
| Pivot attribute access | `$model->pivot->amount` | `$model->getRelation('pivot')->amount` |
| Dynamic/computed attribute | `$model->computed_attr` | `$model->getAttribute('computed_attr')` |
| `Model::delete()` returns `bool\|null` | `return $model->delete();` | `return (bool) $model->delete();` |
| `groupBy()` key type | `Collection<int, ...>` | `Collection<int\|string, ...>` |
| Enum `instanceof` on cast prop | `$user->role instanceof Role` | Always true — just use `$user->role === Role::ADMIN` |

## Builder::where() Column Strings

Larastan validates column names in `Builder::where('column', ...)` calls against model properties. When using `@template TModel of Model` on generic query methods, Larastan resolves columns against the base `Model` class (which has no columns). This is a known Larastan limitation. Suppress with:

```php
$query->where('column_name', $value); // @phpstan-ignore argument.type
```

Only use this ignore for Builder column string errors, never for other argument.type issues.
