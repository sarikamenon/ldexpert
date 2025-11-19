<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolContract extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => ContractStatus::class,
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(SchoolContractService::class);
    }
}
