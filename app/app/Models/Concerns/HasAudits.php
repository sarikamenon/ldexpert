<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Domain\Audit\Services\AuditBatchContext;
use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Records "updated" and "deleted" model events to the audits table.
 *
 * Opt in by adding `use HasAudits;` to a model. Override the protected
 * properties below to narrow / widen the audited field set.
 *
 * @phpstan-require-extends Model
 */
trait HasAudits
{
    /**
     * Fields baked into the trait that must never appear in audit values
     * regardless of model. Sensitive credentials + bookkeeping columns.
     *
     * @var array<int, string>
     */
    private static array $globalAuditIgnoreFields = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected static function bootHasAudits(): void
    {
        static::updating(function (Model $model): void {
            /** @var self&Model $model */
            $model->recordAuditForEvent('updated');
        });

        static::deleting(function (Model $model): void {
            /** @var self&Model $model */
            $model->recordAuditForEvent('deleted');
        });
    }

    /** @return MorphMany<Audit, $this> */
    public function audits(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable');
    }

    /**
     * Override on the model to audit only a subset of columns.
     *
     * @return array<int, string>
     */
    protected function resolveAuditFields(): array
    {
        // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType
        if (property_exists($this, 'auditFields') && is_array($this->auditFields)) {
            /** @var array<int, string> $fields */
            $fields = $this->auditFields;

            return $fields;
        }

        /** @var array<int, string> $columns */
        $columns = $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());

        return $columns;
    }

    /** @return array<int, string> */
    protected function resolveAuditIgnoreFields(): array
    {
        $ignore = self::$globalAuditIgnoreFields;

        // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType
        if (property_exists($this, 'auditIgnoreFields') && is_array($this->auditIgnoreFields)) {
            /** @var array<int, string> $extra */
            $extra = $this->auditIgnoreFields;
            $ignore = array_merge($ignore, $extra);
        }

        return array_values(array_unique($ignore));
    }

    protected function recordAuditForEvent(string $event): void
    {
        $fields = array_values(array_diff($this->resolveAuditFields(), $this->resolveAuditIgnoreFields()));

        if ($event === 'updated') {
            $dirtyKeys = array_keys($this->getDirty());
            $fields = array_values(array_intersect($fields, $dirtyKeys));
        }

        if ($fields === []) {
            return;
        }

        [$oldValues, $newValues] = $this->buildAuditValues($fields, $event);
        [$oldValues, $newValues] = $this->sanitizeAuditValues($oldValues, $newValues);

        if (($oldValues === null || $oldValues === []) && ($newValues === null || $newValues === [])) {
            return;
        }

        $this->audits()->create(array_merge([
            'created_by' => $this->resolveAuditUserId(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'batch_uuid' => $this->resolveAuditBatchUuid(),
            'source' => $this->resolveAuditSource(),
            'url' => $this->resolveAuditUrl(),
            'ip_address' => $this->resolveAuditIpAddress(),
            'user_agent' => $this->resolveAuditUserAgent(),
        ], $this->resolveExtraAuditFields()));
    }

    /**
     * @param  array<int, string>  $fields
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    protected function buildAuditValues(array $fields, string $event): array
    {
        if ($event === 'deleted') {
            $oldValues = [];
            foreach ($fields as $field) {
                $oldValues[$field] = $this->normalizeAuditValue($this->getOriginal($field), $field);
            }

            return [$oldValues, null];
        }

        $oldValues = [];
        $newValues = [];

        foreach ($fields as $field) {
            $old = $this->normalizeAuditValue($this->getOriginal($field), $field);
            $new = $this->normalizeAuditValue($this->getAttribute($field), $field);

            if ($this->auditValuesEqual($old, $new)) {
                continue;
            }

            $oldValues[$field] = $old;
            $newValues[$field] = $new;
        }

        return [$oldValues, $newValues];
    }

    protected function auditValuesEqual(mixed $old, mixed $new): bool
    {
        if (is_array($old) || is_array($new) || is_object($old) || is_object($new)) {
            return json_encode($old) === json_encode($new);
        }

        return $old === $new;
    }

    /**
     * Override on the model to strip or transform audit values before storing.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    protected function sanitizeAuditValues(?array $oldValues, ?array $newValues): array
    {
        return [$oldValues, $newValues];
    }

    protected function normalizeAuditValue(mixed $value, ?string $field = null): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            // Pure calendar dates (cast: 'date') store as YYYY-MM-DD without
            // an artificial UTC instant suffix. Datetime-cast columns keep
            // full ISO-8601 so timezone information is preserved.
            return $this->isDateOnlyCast($field)
                ? $value->format('Y-m-d')
                : $value->format(\DateTimeInterface::ATOM);
        }

        return $value;
    }

    private function isDateOnlyCast(?string $field): bool
    {
        if ($field === null) {
            return false;
        }

        $casts = $this->getCasts();
        $cast = $casts[$field] ?? null;

        if (! is_string($cast)) {
            return false;
        }

        return $cast === 'date' || $cast === 'immutable_date' || str_starts_with($cast, 'date:');
    }

    protected function resolveAuditUserId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    protected function resolveAuditBatchUuid(): ?string
    {
        return app(AuditBatchContext::class)->current();
    }

    protected function resolveAuditSource(): ?string
    {
        $override = app(AuditBatchContext::class)->source();
        if ($override !== null) {
            return $override;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'console';
        }

        return 'web';
    }

    protected function resolveAuditIpAddress(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request()->ip();
    }

    protected function resolveAuditUserAgent(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request()->userAgent();
    }

    protected function resolveAuditUrl(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        $url = request()->fullUrl();

        return mb_substr($url, 0, 2048);
    }

    /**
     * Override on the model to add extra columns to the audit record.
     *
     * @return array<string, mixed>
     */
    protected function resolveExtraAuditFields(): array
    {
        return [];
    }
}
