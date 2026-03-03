<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RateType;
use App\Enums\Role;
use App\Enums\SessionLogCommentType;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Carbon $session_date
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property SessionOutcome|null $outcome
 */
class SessionLog extends Model
{
    /** @use HasFactory<\Database\Factories\SessionLogFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'therapist_id',
        'student_id',
        'ssa_id',
        'schedule_id',
        'service_id',
        'school_id',
        'session_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'tho_minutes',
        'notes',
        'delivery_mode',
        'outcome',
        'is_group',
        'is_billable_therapist',
        'therapist_contract_id',
        'therapist_rate_type',
        'therapist_rate_amount',
        'therapist_billable_amount',
        'therapist_bill_id',
        'is_billable_school',
        'school_contract_id',
        'school_rate_type',
        'school_rate_amount',
        'school_invoice_amount',
        'invoice_id',
        'is_rate_override',
        'override_reason',
        'status',
        'submitted_at',
        'submitted_by_id',
        'approved_at',
        'approved_by_id',
        'sent_back_at',
        'sent_back_by_id',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'tho_minutes' => 'integer',
            'delivery_mode' => 'string',
            'outcome' => SessionOutcome::class,
            'is_group' => 'boolean',
            'is_billable_therapist' => 'boolean',
            'therapist_rate_type' => RateType::class,
            'therapist_rate_amount' => 'decimal:2',
            'therapist_billable_amount' => 'decimal:2',
            'is_billable_school' => 'boolean',
            'school_rate_type' => RateType::class,
            'school_rate_amount' => 'decimal:2',
            'school_invoice_amount' => 'decimal:2',
            'is_rate_override' => 'boolean',
            // Status is handled via custom accessor/mutator; do not also use enum cast
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_back_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<ServiceSupportAgreement, $this> */
    public function ssa(): BelongsTo
    {
        return $this->belongsTo(ServiceSupportAgreement::class, 'ssa_id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /** @return BelongsTo<TherapistContract, $this> */
    public function therapistContract(): BelongsTo
    {
        return $this->belongsTo(TherapistContract::class, 'therapist_contract_id');
    }

    /** @return BelongsTo<SchoolContract, $this> */
    public function schoolContract(): BelongsTo
    {
        return $this->belongsTo(SchoolContract::class, 'school_contract_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sentBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_back_by_id');
    }

    /** @return HasMany<SessionLogComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(SessionLogComment::class, 'session_log_id');
    }

    public function getLatestSentBackComment(): ?SessionLogComment
    {
        return $this->comments()
            ->where('type', SessionLogCommentType::SENT_BACK)
            ->orderByDesc('created_at')
            ->first();
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /** @return BelongsTo<TherapistBill, $this> */
    public function therapistBill(): BelongsTo
    {
        return $this->belongsTo(TherapistBill::class, 'therapist_bill_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\StudentDocument, $this> */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\StudentDocument::class, 'documentable');
    }

    public function isDraft(): bool
    {
        return $this->status === SessionLogStatus::DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === SessionLogStatus::SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === SessionLogStatus::APPROVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === SessionLogStatus::CANCELLED;
    }

    public function isSentBack(): bool
    {
        return $this->status === SessionLogStatus::SENT_BACK;
    }

    public function getStatusAttribute(mixed $value): ?SessionLogStatus
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SessionLogStatus) {
            return $value;
        }

        return SessionLogStatus::tryFrom((string) $value);
    }

    public function setStatusAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['status'] = null;

            return;
        }

        if ($value instanceof SessionLogStatus) {
            $this->attributes['status'] = $value->value;

            return;
        }

        // Try to convert string to enum
        $enum = SessionLogStatus::tryFrom((string) $value);
        $this->attributes['status'] = $enum->value ?? $value;
    }

    public function canEdit(): bool
    {
        return $this->status?->canEdit() ?? false;
    }

    public function calculateDurationMinutes(): int
    {
        // Round to nearest 5 minutes
        $minutes = (int) $this->start_time->diffInMinutes($this->end_time);

        return (int) round($minutes / 5) * 5;
    }

    /**
     * Scope route model binding so therapists can only resolve their own
     * session logs on therapist routes. Admin routes continue to resolve
     * by ID only.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->newQuery();
        $field ??= $this->getRouteKeyName();

        $route = request()->route();
        $routeName = $route?->getName();
        $isTherapistRoute = is_string($routeName) && str_starts_with($routeName, 'therapist.');

        /** @var \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard $auth */
        $auth = auth();
        $user = $auth->user();

        if ($isTherapistRoute && $user instanceof User) {
            if ($user->role === Role::THERAPIST) {
                $query->where('therapist_id', $user->id);
            }
        }

        return $query->where($field, $value)->firstOrFail();
    }
}
