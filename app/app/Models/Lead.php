<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Scopes\LeadScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property LeadStatus $status
 * @property LeadSource|null $source
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $follow_up_date
 * @property Carbon|null $converted_at
 */
class Lead extends Model
{
    /** @use HasFactory<\Database\Factories\LeadFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'gender',
        'date_of_birth',
        'school_id',
        'grade_level',
        'parent_guardian_name',
        'parent_guardian_email',
        'parent_guardian_phone',
        'address',
        'city',
        'state',
        'zip_code',
        'status',
        'status_reason',
        'source',
        'follow_up_date',
        'follow_up_notes',
        'created_by',
        'converted_student_id',
        'converted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'date_of_birth' => 'date',
            'follow_up_date' => 'date',
            'converted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function convertedStudent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_student_id');
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<LeadNote, $this> */
    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return implode(' ', $parts);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return LeadScope::search($query, $term);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        return LeadScope::byStatus($query, $status);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeOverdueFollowUp(Builder $query): Builder
    {
        return LeadScope::overdueFollowUp($query);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeFollowUpDueOn(Builder $query, string $date): Builder
    {
        return LeadScope::followUpDueOn($query, $date);
    }
}
