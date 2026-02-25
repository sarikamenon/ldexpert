<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\StudentScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property \Carbon\Carbon|null $date_of_birth
 */
class StudentProfile extends Model
{
    /** @use HasFactory<\Database\Factories\StudentProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_id',
        'first_name',
        'middle_name',
        'last_name',
        'school_id',
        'id_number',
        'timezone',
        'gender',
        'address',
        'city',
        'state',
        'zip_code',
        'parent_guardian_name',
        'parent_guardian_email',
        'parent_guardian_phone',
        'schedule_email',
        'date_of_birth',
        'grade_level',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<ServiceSupportAgreement, $this> */
    public function ssas(): HasMany
    {
        return $this->hasMany(ServiceSupportAgreement::class, 'student_id', 'user_id');
    }

    /**
     * @param  Builder<StudentProfile>  $query
     * @return Builder<StudentProfile>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return StudentScope::search($query, $term);
    }

    /**
     * @param  Builder<StudentProfile>  $query
     * @return Builder<StudentProfile>
     */
    public function scopeActive(Builder $query): Builder
    {
        return StudentScope::active($query, $this);
    }

    /**
     * @param  Builder<StudentProfile>  $query
     * @return Builder<StudentProfile>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return StudentScope::inactive($query, $this);
    }
}
