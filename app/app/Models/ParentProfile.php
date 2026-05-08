<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAudits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentProfile extends Model
{
    use HasAudits;

    /** @use HasFactory<\Database\Factories\ParentProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'emergency_contact',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudentProfile, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'parent_id', 'user_id');
    }
}
