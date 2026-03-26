Scaffold all required files for a new feature following CLAUDE.md architecture standards.

Arguments: $ARGUMENTS should contain: <DomainName> <ModelName> [--role=admin|therapist|student]

Steps:
1. Parse the domain name, model name, and role (default: admin) from arguments
2. Create the following files (skip any that already exist):
   - `app/Domain/<DomainName>/Services/<ModelName>Service.php` — service class with constructor DI for repository
   - `app/Domain/<DomainName>/Repositories/<ModelName>RepositoryInterface.php` — interface with CRUD + listForDataTables
   - `app/Infrastructure/Repositories/Eloquent<ModelName>Repository.php` — Eloquent implementation
   - `app/DTOs/<ModelName>FilterDTO.php` — filter DTO with fromArray/toArray
   - `app/Http/Controllers/<Role>/<ModelName>Controller.php` — controller delegating to service, using authorize()
   - `app/Http/Requests/<Role>/<ModelName>/Store<ModelName>Request.php` — create form request
   - `app/Http/Requests/<Role>/<ModelName>/Update<ModelName>Request.php` — update form request
   - `app/Policies/<ModelName>Policy.php` — policy with viewAny, view, create, update, delete
   - `app/DataTables/Transformers/<ModelName>RowTransformer.php` — DataTable row transformer
3. Register the repository binding in AppServiceProvider
4. Register the policy in AppServiceProvider
5. Add route stubs to the appropriate role route file
6. All files must follow CLAUDE.md standards (strict types, PHPStan L8, generics, etc.)
7. List all created files and remind to run `make qa`
