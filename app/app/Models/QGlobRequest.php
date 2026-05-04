<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QGlobRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property \Carbon\CarbonInterface $requested_date
 * @property string $requested_time
 * @property QGlobRequestStatus $status
 */
class QGlobRequest extends Model
{
    /** @use HasFactory<\Database\Factories\QGlobRequestFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'qglob_requests';

    protected $fillable = [
        'requested_by_id',
        'student_id',
        'requested_date',
        'requested_time',
        'note',
        'status',
        'admin_response',
        'responded_by_id',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'requested_time' => 'string',
            'status' => QGlobRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }
}
