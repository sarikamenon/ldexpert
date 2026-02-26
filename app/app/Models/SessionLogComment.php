<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionLogCommentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionLogComment extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'session_log_id',
        'author_id',
        'comment',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => SessionLogCommentType::class,
        ];
    }

    /** @return BelongsTo<SessionLog, $this> */
    public function sessionLog(): BelongsTo
    {
        return $this->belongsTo(SessionLog::class, 'session_log_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
