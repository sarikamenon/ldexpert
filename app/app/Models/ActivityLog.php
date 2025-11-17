<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'changes',
        'description',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    public function getFormattedChangesAttribute(): ?string
    {
        if (!$this->changes) {
            return null;
        }

        $formatted = [];
        foreach ($this->changes as $key => $change) {
            if (isset($change['old'], $change['new'])) {
                $formatted[] = ucfirst(str_replace('_', ' ', $key)) . ': ' 
                    . $change['old'] . ' → ' . $change['new'];
            }
        }

        return implode(', ', $formatted);
    }
}

