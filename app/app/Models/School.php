<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\UsStates;
use App\Enums\SchoolStatus;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class School extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'display_name',
        'address',
        'state',
        'timezone',
        'manager_id',
        'contact_first_name',
        'contact_last_name',
        'contact_phone',
        'contact_email',
        'invoice_email',
        'school_type',
        'is_private_student',
        'non_billable_scheduling',
        'external_emr_name',
        'status',
        'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_private_student' => 'boolean',
            'non_billable_scheduling' => 'boolean',
            'status' => SchoolStatus::class,
        ];
    }

    protected function state(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value) => $value ? UsStates::getStateName($value) : null,
            set: fn(?string $value) => $value ? UsStates::getStateName($value) : null,
        );
    }

    protected function stateCode(): Attribute
    {
        return Attribute::make(
            get: fn($value, array $attributes) => $this->resolveStateCode($attributes['state'] ?? null),
        );
    }

    private function resolveStateCode(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = strtoupper($value);
        if (array_key_exists($normalized, UsStates::STATES)) {
            return $normalized;
        }

        foreach (UsStates::STATES as $code => $name) {
            if (strcasecmp($name, $value) === 0) {
                return $code;
            }
        }

        return null;
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return SchoolScope::search($query, $term);
    }

    public function scopeStatus(Builder $query, ?SchoolStatus $status): Builder
    {
        return SchoolScope::status($query, $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return SchoolScope::active($query, $query->getModel());
    }

    public function scopeInactive(Builder $query): Builder
    {
        return SchoolScope::inactive($query, $query->getModel());
    }
}
