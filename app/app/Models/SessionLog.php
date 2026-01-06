<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RateType;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionLog extends Model
{
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
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function ssa(): BelongsTo
    {
        return $this->belongsTo(ServiceSupportAgreement::class, 'ssa_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function therapistContract(): BelongsTo
    {
        return $this->belongsTo(TherapistContract::class, 'therapist_contract_id');
    }

    public function schoolContract(): BelongsTo
    {
        return $this->belongsTo(SchoolContract::class, 'school_contract_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function therapistBill(): BelongsTo
    {
        return $this->belongsTo(TherapistBill::class, 'therapist_bill_id');
    }

    // Documents relationship can be added later when document storage is implemented
    // public function documents(): MorphMany
    // {
    //     return $this->morphMany(SessionLogDocument::class, 'documentable');
    // }

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

    public function getStatusAttribute(mixed $value): ?SessionLogStatus
    {
        return SessionLogStatus::tryFrom((string) $value);
    }

    public function canEdit(): bool
    {
        return $this->isDraft();
    }

    public function calculateDurationMinutes(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        // Round to nearest 5 minutes
        $minutes = (int) $start->diffInMinutes($end);

        return (int) round($minutes / 5) * 5;
    }
}
