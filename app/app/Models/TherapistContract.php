<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TherapistContract extends Model
{
    
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => ContractStatus::class,
    ];

    /** @return BelongsTo<TherapistProfile, $this> */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    /** @return HasMany<TherapistContractService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(TherapistContractService::class);
    }
}
