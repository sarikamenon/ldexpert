---
name: laravel-scaffold
description: Scaffold Laravel features following NOVA's DDD architecture — Controller, Service, Repository, DTO, FormRequest, Policy, RowTransformer, and tests. Use when creating a new module, adding CRUD for a new entity, or setting up a new admin/therapist/student feature. Triggers on "create a new feature", "scaffold", "add CRUD for", "new module", "new entity".
---

# Laravel Feature Scaffold

Scaffold all required files for a new feature following NOVA's DDD + Clean Architecture.

## Architecture Layers

```
Controller (HTTP) → Service (Business Logic) → Repository (Data Access) → Eloquent Model
     ↑                    ↑                         ↑
  FormRequest           DTO                   Interface
  Policy              Enum                   Binding in AppServiceProvider
  RowTransformer
```

## Files to Create

For a new entity `{Entity}` in domain `{Domain}` for role `{Role}`:

### Domain Layer
1. `app/Domain/{Domain}/Repositories/{Entity}RepositoryInterface.php`
   - Methods: `find()`, `create()`, `update()`, `delete()`, `listForDataTables()`
   - Full PHPDoc with generics

2. `app/Domain/{Domain}/Services/{Entity}Service.php`
   - Constructor DI for repository interface
   - Business logic methods delegating to repository

### Infrastructure Layer
3. `app/Infrastructure/Repositories/Eloquent{Entity}Repository.php`
   - Implements the interface
   - Eloquent queries with proper typing

### HTTP Layer
4. `app/Http/Controllers/{Role}/{Entity}Controller.php`
   - Type-hint FormRequests, not `Request`
   - `$this->authorize()` calls
   - Delegate to Service, never inline logic
   - Under 300 lines

5. `app/Http/Requests/{Role}/{Entity}/Store{Entity}Request.php`
6. `app/Http/Requests/{Role}/{Entity}/Update{Entity}Request.php`
   - `declare(strict_types=1)`
   - `@return array<string, array<int, mixed>|string>` on `rules()`

### Authorization
7. `app/Policies/{Entity}Policy.php`
   - Methods: `viewAny()`, `view()`, `create()`, `update()`, `delete()`
   - Register in `AppServiceProvider::boot()`

### DataTables
8. `app/DataTables/Transformers/{Entity}RowTransformer.php`
   - Static `transform($model): array`
   - Returns HTML strings per column
9. `app/DTOs/{Entity}FilterDTO.php`
   - `fromArray()` and `toArray()` methods

### Registration
10. Add repository binding in `AppServiceProvider::register()`
11. Add policy registration in `AppServiceProvider::boot()`
12. Add routes to `routes/{role}.php`

## PHPStan Compliance Checklist

Every generated file MUST:
- Start with `declare(strict_types=1);`
- Have typed parameters and return types
- Include `@return` PHPDoc for generics
- Use `use` statements (never FQCN)
- Model relations: full generic annotations
- Collections: `Collection<int, Model>`

## After Scaffolding

1. Run `make qa` to verify zero PHPStan errors
2. Create migration if model is new
3. Create factory for testing
4. Write tests (unit for Service/DTO, feature for routes)
