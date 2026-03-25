<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\UsStates;
use App\Enums\SchoolStatus;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SchoolStatus $status
 */
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

    /** @return Attribute<string, string> */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->display_name ?? $this->full_name,
        );
    }

    /** @return Attribute<string|null, string|null> */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->contact_email ?? $this->invoice_email,
        );
    }

    /** @return Attribute<string|null, string|null> */
    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->contact_phone,
        );
    }

    /** @return Attribute<string|null, string|null> */
    protected function state(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? UsStates::getStateName($value) : null,
            set: fn (?string $value) => $this->resolveStateCode($value),
        );
    }

    /** @return Attribute<string|null, string|null> */
    protected function stateCode(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $this->resolveStateCode($attributes['state'] ?? null),
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

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return HasMany<StudentProfile, $this> */
    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    /** @return HasMany<SchoolCalendarEvent, $this> */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(SchoolCalendarEvent::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'school_id');
    }

    /** @return MorphMany<BillingSchedule, $this> */
    public function billingSchedules(): MorphMany
    {
        return $this->morphMany(BillingSchedule::class, 'schedulable');
    }

    /** @return MorphMany<\App\Models\LedgerEntry, $this> */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'ledgerable');
    }

    /**
     * @param  Builder<School>  $query
     * @return Builder<School>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return SchoolScope::search($query, $term);
    }

    /**
     * @param  Builder<School>  $query
     * @return Builder<School>
     */
    public function scopeStatus(Builder $query, ?SchoolStatus $status): Builder
    {
        return SchoolScope::status($query, $status);
    }

    /**
     * @param  Builder<School>  $query
     * @return Builder<School>
     */
    public function scopeActive(Builder $query): Builder
    {
        return SchoolScope::active($query, $query->getModel());
    }

    /**
     * @param  Builder<School>  $query
     * @return Builder<School>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return SchoolScope::inactive($query, $query->getModel());
    }
}
