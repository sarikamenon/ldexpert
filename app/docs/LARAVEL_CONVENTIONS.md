# Laravel-First Conventions

This project is a Laravel application, not a "PHP with a framework on top" project. When a Laravel helper, facade, Collection method, or Eloquent feature exists for what you're about to write, **use it**. Reach for native PHP only when no Laravel equivalent fits, and justify the deviation in a code comment.

A May 2026 audit of `app/app/` found ~760 native-PHP idioms that have a clean Laravel replacement. The rules below target the four pervasive categories (date/time, array transforms, strings, inline JSON responses) plus the moderate ones (file I/O, array predicates). New code MUST follow these rules; touched code SHOULD be migrated opportunistically.

---

## 1. Dates & Times — use Carbon, never native PHP

**Banned in new code:** `date()`, `time()`, `strtotime()`, `mktime()`, `microtime()`, `new DateTime()`, `new \DateTime()`.

**Use instead:** `now()`, `today()`, `Carbon::now()`, `Carbon::parse()`, `CarbonImmutable`.

```php
// ❌ Bad
$year     = (int) date('Y');
$now      = time();
$start    = strtotime('-1 week');
$dateTime = new DateTime('2026-05-20');

// ✅ Good
$year     = now()->year;
$now      = now();
$start    = now()->subWeek();
$dateTime = Carbon::parse('2026-05-20');
```

Storage rule: all timestamps go to the database in **UTC** (see CLAUDE.md → Dates & Timezones). Display conversion goes through `App\Domain\Time\UserTimezoneService`. Never use MySQL `CONVERT_TZ` as on all environment convert_tz is not available on mysql.

---

## 2. Array transformations — use Collections, never `array_map` / `array_filter`

**Banned in new code:** `array_map`, `array_filter`, `array_reduce`, `array_combine`, `array_column`, `array_unique`, `array_diff`, `array_intersect`, `usort`, `uasort`, and especially the `array_values(array_filter(array_map(...)))` chain.

**Use instead:** wrap in `collect()` and chain Collection methods.

```php
// ❌ Bad — the pervasive nested anti-pattern
$statuses = array_values(array_filter(array_map(
    static fn ($v) => $v instanceof SSAStatus ? $v : SSAStatus::tryFrom((string) $v),
    $rawStatuses
)));

// ✅ Good
$statuses = collect($rawStatuses)
    ->map(fn ($v) => $v instanceof SSAStatus ? $v : SSAStatus::tryFrom((string) $v))
    ->filter()
    ->values()
    ->all();
```

```php
// ❌ Bad — enum case mapping (most common offender)
return array_map(static fn (self $c): string => $c->value, self::cases());

// ✅ Good
return collect(self::cases())->pluck('value')->all();
```

```php
// ❌ Bad — foreach that builds an array
$names = [];
foreach ($users as $user) {
    $names[] = $user->name;
}

// ✅ Good
$names = $users->pluck('name')->all();
// or, for a non-Eloquent array:
$names = collect($users)->map(fn ($u) => $u->name)->all();
```

`foreach` is allowed **only for pure side effects** (DB writes inside the loop, dispatching events, logging). Anything that returns a new array or value must use Collections.

### Common replacements

| Native PHP                              | Laravel                                          |
|-----------------------------------------|--------------------------------------------------|
| `array_map($fn, $arr)`                  | `collect($arr)->map($fn)->all()`                 |
| `array_filter($arr)`                    | `collect($arr)->filter()->values()->all()`       |
| `array_values(array_filter(...))`       | `->filter()->values()`                           |
| `array_reduce($arr, $fn, $initial)`     | `collect($arr)->reduce($fn, $initial)`           |
| `array_combine($keys, $values)`         | `collect($keys)->combine($values)`               |
| `array_column($arr, 'id')`              | `collect($arr)->pluck('id')->all()`              |
| `array_unique($arr)`                    | `collect($arr)->unique()->values()->all()`       |
| `array_diff($a, $b)`                    | `collect($a)->diff($b)->values()->all()`         |
| `array_intersect($a, $b)`               | `collect($a)->intersect($b)->values()->all()`    |
| `usort($arr, $cmp)`                     | `collect($arr)->sortBy($key)->values()->all()`   |
| `count($arr) === 0` / `empty($arr)`     | `collect($arr)->isEmpty()` (or `$arr === []`)    |
| `in_array($x, $arr, true)`              | `collect($arr)->contains($x)`                    |

---

## 3. Strings — use `Str::` and Collection joins

**Banned in new code:** raw `str_replace`, `strpos`, `stripos`, `substr`, `strtolower`, `strtoupper`, `ucfirst`, `ucwords`, `explode`, `implode` (when working with a Collection/array of model values), `preg_match` / `preg_replace` for simple matches.

**Use instead:** the `Str` facade or the `str()` helper, and `Collection::join()`.

```php
// ❌ Bad
$slug   = strtolower(str_replace(' ', '-', $title));
$joined = implode(', ', $parts);
$parts  = explode(',', $csv);
$has    = strpos($email, '@') !== false;
$first  = substr($name, 0, 1);
$upper  = ucfirst($word);

// ✅ Good
$slug   = Str::slug($title);                       // or str($title)->slug()
$joined = collect($parts)->join(', ');             // human-readable: ->join(', ', ' and ')
$parts  = Str::of($csv)->explode(',');             // returns a Collection
$has    = Str::contains($email, '@');
$first  = Str::substr($name, 0, 1);                // when you really need substr semantics
$upper  = Str::ucfirst($word);
```

Use `Str::` (facade) or `str()` (fluent helper) — pick one per file and stick with it. The fluent form is preferred for chains: `str($title)->lower()->slug()->limit(50)`.

`implode()` is fine on a small literal array of strings you constructed inline. The rule targets `implode(...)` over data — pluck/map results, model fields, collection output — which should use `->join()`.

---

## 4. Controller responses — API Resources or `RowTransformer`, never inline `response()->json([...])`

This rule is **already** in CLAUDE.md's pre-commit checklist but is the #4 violation category (111 hits), so it's restated here with examples.

```php
// ❌ Bad
public function show(Student $student): JsonResponse
{
    return response()->json([
        'id'    => $student->id,
        'name'  => $student->full_name,
        'email' => $student->user->email,
    ]);
}

// ✅ Good — extract to App\Http\Resources\StudentResource
public function show(Student $student): StudentResource
{
    return new StudentResource($student);
}
```

For DataTables endpoints, use a `RowTransformer` (see `app/docs/DATATABLES_SERVER_SIDE.md`), not inline JSON.

**Narrow exceptions** (inline `response()->json` is acceptable):

- Webhook acknowledgments: `return response()->json(['status' => 'ok']);`
- Error envelopes: `return response()->json(['message' => 'Not allowed'], 403);`
- Trivial boolean ping endpoints

Anything that returns a model, a list of models, or a structured DTO MUST go through a Resource.

---

## 5. Files — use `Storage` / `File` / the uploaded-file object

**Banned in new code:** `file_get_contents`, `file_put_contents`, `fopen`/`fread`/`fwrite`, `file_exists`, `is_file`, `unlink`, `mkdir`, `rmdir`.

**Use instead:** the `Storage` facade for app-managed files, the `File` facade for arbitrary filesystem paths, and the `UploadedFile` API for uploads.

```php
// ❌ Bad
$contents = file_get_contents($uploadedFile->getRealPath());
file_put_contents(storage_path('app/foo.txt'), $data);
if (file_exists($path)) { ... }

// ✅ Good
$contents = $uploadedFile->get();                  // for UploadedFile
$contents = Storage::get('foo.txt');               // for managed disks
Storage::put('foo.txt', $data);
if (Storage::exists('foo.txt')) { ... }

// For absolute paths outside any disk:
$contents = File::get($absolutePath);
File::exists($absolutePath);
```

Imports from `League\Csv` or similar are fine — wrap the `UploadedFile` rather than reading bytes manually.

---

## 6. Environment & config

**Banned outside `config/`:** `env(...)`, `getenv(...)`, `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER` (except `$_SERVER` for narrow runtime introspection).

**Use instead:** `config('app.name')` for env values (so config-caching works), and a Form Request or `request()` helper for HTTP input.

```php
// ❌ Bad — outside a config/ file
$key = env('STRIPE_SECRET');

// ✅ Good — config/services.php reads env(), code reads config()
$key = config('services.stripe.secret');
```

---

## 7. JSON

- Persisting JSON on a model → use `$casts = ['payload' => 'array']` or `'json'`.
- Embedding JSON in Blade → use `{{ Js::from($data) }}`.
- Calling external HTTP → use `Http::get(...)->json()`; never `json_decode(file_get_contents($url))`.
- Manual `json_encode` / `json_decode` in services is acceptable only when none of the above apply (e.g., signing a webhook payload, hashing a canonicalized blob). Add a one-line comment explaining why.

---

## 8. Hashing, random, UUIDs

| Native PHP                | Laravel                        |
|---------------------------|--------------------------------|
| `md5(...)`, `sha1(...)`   | `Hash::make()` for passwords; `hash('sha256', ...)` for non-secret digests with a comment |
| `uniqid()`                | `Str::uuid()` or `Str::ulid()` |
| `mt_rand()`, `rand()`     | `random_int()` or `Str::random()` |

`md5`/`sha1` for non-security checksums (cache keys, ETags) is acceptable — add a `// non-cryptographic checksum` comment so reviewers don't flag it.

---

## 9. Transactions, queues, scheduling, HTTP

These are already idiomatic in the codebase — keep them that way:

- **Transactions:** use `DB::transaction(fn () => ...)`, never manual `beginTransaction` / `commit` / `rollback`. (Audit: 60 uses, 0 violations ✓)
- **HTTP calls:** use the `Http` facade, never `curl_*` or `file_get_contents('http://...')`.
- **Scheduling:** use `app/Console/Kernel.php` `schedule()`, never OS cron entries that bypass artisan.
- **Async:** use queued jobs or events, never `exec()` / `shell_exec()` for app work.

---

## How to apply this when modifying existing code

- **New code MUST follow these rules** — no exceptions for "matching surrounding style."
- **Touched code SHOULD be migrated.** If you're editing a method that contains an `array_map` chain or a `date()` call, refactor it in the same commit. Don't rewrite the whole file, but don't leave the line you just touched in the old style either.
- **Justify deviations in a comment.** If a native call is genuinely the right choice (perf-critical hot loop measured to be slower with Collections, library boundary requires a specific shape), say so on the line above:
  ```php
  // Native array_map: this runs in a tight loop over 100k+ rows; Collections benchmarked 3x slower.
  $values = array_map(static fn ($r) => $r->id, $rows);
  ```

---

## Pre-commit checklist additions

Add to the existing checklist in CLAUDE.md:

- [ ] No `date()` / `time()` / `strtotime()` / `new DateTime` in new or touched lines — use Carbon / `now()`
- [ ] No `array_map` / `array_filter` / `array_reduce` chains — use `collect()`
- [ ] No `foreach` that builds an array — use `map` / `pluck` / `flatMap`
- [ ] No raw `str_*` / `implode` over data — use `Str::` or `->join()`
- [ ] No inline `response()->json([...])` returning structured data — use a Resource
- [ ] No `file_get_contents` / `file_exists` — use `Storage`, `File`, or `UploadedFile::get()`
- [ ] No `env()` outside `config/` — use `config()`
