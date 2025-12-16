<?php

declare(strict_types=1);

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SSAService extends Pivot
{
    use SoftDeletes;

    protected $table = 'ssa_services';

    public $timestamps = true;

    protected $fillable = [
        'ssa_id',
        'service_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'bool',
    ];
}
