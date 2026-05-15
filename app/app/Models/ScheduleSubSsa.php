<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduleSubSsa extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleSubSsaFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'schedule_sub_request_id',
        'schedule_id',
        'ssa_id',
        'sub_therapist_id',
        'student_id',
        'service_id',
        'school_id',
        'session_date',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ScheduleSubRequest, $this> */
    public function subRequest(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubRequest::class, 'schedule_sub_request_id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /** @return BelongsTo<ServiceSupportAgreement, $this> */
    public function ssa(): BelongsTo
    {
        return $this->belongsTo(ServiceSupportAgreement::class, 'ssa_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subTherapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sub_therapist_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
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

    /**
     * @param  Builder<ScheduleSubSsa>  $query
     * @return Builder<ScheduleSubSsa>
     */
    public function scopeForSchedule(Builder $query, int $scheduleId): Builder
    {
        return $query->where('schedule_id', $scheduleId);
    }

    /**
     * @param  Builder<ScheduleSubSsa>  $query
     * @return Builder<ScheduleSubSsa>
     */
    public function scopeForSubTherapist(Builder $query, int $therapistId): Builder
    {
        return $query->where('sub_therapist_id', $therapistId);
    }

    /**
     * @param  Builder<ScheduleSubSsa>  $query
     * @return Builder<ScheduleSubSsa>
     */
    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * @param  Builder<ScheduleSubSsa>  $query
     * @return Builder<ScheduleSubSsa>
     */
    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }

    /**
     * @param  Builder<ScheduleSubSsa>  $query
     * @return Builder<ScheduleSubSsa>
     */
    public function scopeForSessionDate(Builder $query, string $date): Builder
    {
        return $query->where('session_date', $date);
    }
}
