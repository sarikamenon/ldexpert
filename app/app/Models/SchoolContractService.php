<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolContractService extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:2',
        'rate_type' => RateType::class,
        'no_show_rate' => 'decimal:2',
        'no_show_rate_type' => RateType::class,
    ];

    /** @return BelongsTo<SchoolContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(SchoolContract::class, 'school_contract_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
