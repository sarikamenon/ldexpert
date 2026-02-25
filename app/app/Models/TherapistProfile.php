<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployeeType;
use App\Enums\TherapistTitle;
use App\Models\Scopes\TherapistScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TherapistProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_type',
        'title',
        'first_name',
        'last_name',
        'personal_email',
        'phone',
        'ld_email',
        'address',
        'comments',
        'position_id',
        'state',
        'timezone',
        'manager_id',
        'max_weekly_hours',
        'hourly_rate',
        'dob',
        'default_meeting_location',
    ];

    protected function casts(): array
    {
        return [
            'employee_type' => EmployeeType::class,
            'title' => TherapistTitle::class,
            'max_weekly_hours' => 'integer',
            'hourly_rate' => 'decimal:2',
            'dob' => 'date',
        ];
    }

    /** @return BelongsTo<User, TherapistProfile> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, TherapistProfile> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @param  Builder<TherapistProfile>  $query
     * @return Builder<TherapistProfile>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return TherapistScope::search($query, $term);
    }

    /**
     * @param  Builder<TherapistProfile>  $query
     * @return Builder<TherapistProfile>
     */
    public function scopeActive(Builder $query): Builder
    {
        return TherapistScope::active($query, $this);
    }

    /**
     * @param  Builder<TherapistProfile>  $query
     * @return Builder<TherapistProfile>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return TherapistScope::inactive($query, $this);
    }
}
