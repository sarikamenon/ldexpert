<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Enums\ServiceFrequency;
use App\Enums\SSAStatus;
use App\Models\Pivots\SSAService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceSupportAgreement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'primary_service_id',
        'start_date',
        'end_date',
        'minutes_per_session',
        'frequency',
        'sessions_per_frequency',
        'calculated_minutes',
        'adjusted_minutes',
        'adjustment_notes',
        'tho_minutes',
        'assigned_therapist_id',
        'status',
        'served_minutes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'minutes_per_session' => 'integer',
            'frequency' => ServiceFrequency::class,
            'sessions_per_frequency' => 'integer',
            'calculated_minutes' => 'integer',
            'adjusted_minutes' => 'integer',
            'tho_minutes' => 'integer',
            'status' => SSAStatus::class,
            'served_minutes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, ServiceSupportAgreement> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<Service, ServiceSupportAgreement> */
    public function primaryService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'primary_service_id');
    }

    /** @return BelongsToMany<Service, ServiceSupportAgreement> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'ssa_services', 'ssa_id', 'service_id')
            ->using(SSAService::class)
            ->withPivot(['is_primary', 'deleted_at'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Service, ServiceSupportAgreement> */
    public function additionalServices(): BelongsToMany
    {
        return $this->services()->wherePivot('is_primary', false);
    }

    /** @return BelongsTo<User, ServiceSupportAgreement> */
    public function assignedTherapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_therapist_id');
    }

    /** @return HasMany<SSAAssignmentHistory, ServiceSupportAgreement> */
    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(SSAAssignmentHistory::class, 'ssa_id')->orderBy('created_at', 'desc');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SSAStatus::PENDING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SSAStatus::ACTIVE);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SSAStatus::COMPLETED);
    }

    public function scopeDeactivated(Builder $query): Builder
    {
        return $query->where('status', SSAStatus::DEACTIVATED);
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('assigned_therapist_id');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_therapist_id');
    }

    public function canBeActivated(): bool
    {
        return $this->assigned_therapist_id !== null;
    }

    public function calculateThoMinutes(): int
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);
        $daysDiff = $startDate->diffInDays($endDate) + 1;

        $frequencyMultiplier = match ($this->frequency) {
            ServiceFrequency::WEEKLY => 52 / 365,
            ServiceFrequency::BI_WEEKLY => 26 / 365,
            ServiceFrequency::MONTHLY => 12 / 365,
            ServiceFrequency::QUARTERLY => 4 / 365,
        };

        $numberOfFrequencies = (int) ceil($daysDiff * $frequencyMultiplier);
        $totalSessions = $numberOfFrequencies * $this->sessions_per_frequency;

        return $totalSessions * $this->minutes_per_session;
    }

    /**
     * Scope route model binding so therapists can only resolve SSAs
     * assigned to them on therapist routes. Admin routes continue to
     * resolve by ID only.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->newQuery();
        $field ??= $this->getRouteKeyName();

        $route = request()?->route();
        $routeName = $route?->getName();
        $isTherapistRoute = is_string($routeName) && str_starts_with($routeName, 'therapist.');

        /** @var \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard $auth */
        $auth = auth();
        $user = $auth->user();

        if ($isTherapistRoute && $user instanceof User) {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            if ($role === Role::THERAPIST) {
                $query->where('assigned_therapist_id', $user->id);
            }
        }

        return $query->where($field, $value)->firstOrFail();
    }
}
