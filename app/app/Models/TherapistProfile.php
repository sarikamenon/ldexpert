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
        'position',
        'state',
        'timezone',
        'manager_id',
        'max_weekly_hours',
        'dob',
        'default_meeting_location',
    ];

    protected function casts(): array
    {
        return [
            'employee_type' => EmployeeType::class,
            'title' => TherapistTitle::class,
            'max_weekly_hours' => 'integer',
            'dob' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return TherapistScope::search($query, $term);
    }

    public function scopeActive(Builder $query): Builder
    {
        return TherapistScope::active($query, $this);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return TherapistScope::inactive($query, $this);
    }
}
