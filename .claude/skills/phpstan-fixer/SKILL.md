---
name: phpstan-fixer
description: Fix PHPStan Level 8 errors in the NOVA Laravel project. Use when PHPStan analysis reports errors, when `make qa` fails on static analysis, or when the user asks to fix type errors. Triggers on "fix phpstan", "fix type errors", "make qa is failing", "static analysis errors", or when PHPStan output is pasted.
---

# PHPStan L8 Fixer

Fix PHPStan Level 8 errors following NOVA project conventions. See `app/docs/PHPSTAN_RULES.md` for the full reference.

## Diagnosis Flow

1. Run `make qa` or PHPStan directly via Docker
2. Parse errors by identifier (use `_local_docs/phpstan-scripts/categorize-errors.sh`)
3. Fix by category, starting with highest-count errors

## Fix Patterns by Error Identifier

### `missingType.iterableValue` (most common)
Add `@return` or `@param` PHPDoc with generics:
```php
/** @return array<string, mixed> */
/** @return Collection<int, User> */
/** @param array<int, string> $ids */
```

### `missingType.return`
Add native return type + PHPDoc if generic:
```php
public function getItems(): Collection  // needs @return
```

### `argument.type` on Builder::where()
Column string errors from Larastan — suppress only for Builder column strings:
```php
$query->where('column_name', $value); // @phpstan-ignore argument.type
```

### `method.notFound`
Usually a missing relationship or scope. Add the relationship/scope method to the model.

### `property.notFound`
Dynamic/computed attributes — use `getAttribute()`:
```php
$model->getAttribute('computed_attr')
```

### `return.type`
Return type mismatch — check if method signature matches PHPDoc and actual return.

### `nullsafe.neverNull`
Using `?->` on a non-nullable property. Remove the `?`:
```php
// Wrong: $model->status?->value (status is non-nullable enum)
// Right: $model->status->value
```

### `isset.property` / `booleanNot.alwaysTrue`
Redundant null checks on non-nullable properties. Remove the check.

## Common Model Fixes

### Relations — add generics:
```php
/** @return HasMany<SessionLog, $this> */
public function sessionLogs(): HasMany { ... }
```

### HasFactory — add @use:
```php
/** @use HasFactory<\Database\Factories\UserFactory> */
use HasFactory;
```

### Scopes — type both params:
```php
/** @param Builder<User> $query @return Builder<User> */
public function scopeActive(Builder $query): Builder { ... }
```

## Batch Fix Strategy

1. Fix `missingType.iterableValue` first (usually 50%+ of errors)
2. Fix `argument.type` on Builder columns (suppress with `@phpstan-ignore`)
3. Fix `return.type` mismatches
4. Fix model relation generics
5. Run `make qa` after each batch to verify progress
