<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SSAGoalStatus;
use App\Models\Concerns\HasAudits;
use App\Models\Scopes\SSAGoalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $ssa_id
 * @property int $student_id
 * @property string $number
 * @property string $objective
 * @property string|null $progress
 * @property SSAGoalStatus $status
 * @property bool|null $can_transition_status Pre-computed in service for list views; not persisted.
 */
final class SSAGoal extends Model
{
    /** @use HasFactory<\Database\Factories\SSAGoalFactory> */
    use HasAudits, HasFactory, SoftDeletes;

    protected $table = 'ssa_goals';

    protected $fillable = [
        'ssa_id',
        'student_id',
        'number',
        'objective',
        'progress',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SSAGoalStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ServiceSupportAgreement, $this> */
    public function ssa(): BelongsTo
    {
        return $this->belongsTo(ServiceSupportAgreement::class, 'ssa_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @param  Builder<SSAGoal>  $query
     * @return Builder<SSAGoal>
     */
    public function scopeActiveStatus(Builder $query): Builder
    {
        return SSAGoalScope::activeStatus($query, $this);
    }

    /**
     * @param  Builder<SSAGoal>  $query
     * @return Builder<SSAGoal>
     */
    public function scopeForSsa(Builder $query, int $ssaId): Builder
    {
        return SSAGoalScope::forSsa($query, $this, $ssaId);
    }

    /**
     * @param  Builder<SSAGoal>  $query
     * @return Builder<SSAGoal>
     */
    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return SSAGoalScope::forStudent($query, $this, $studentId);
    }

    /**
     * @param  Builder<SSAGoal>  $query
     * @return Builder<SSAGoal>
     */
    public function scopeOrderForList(Builder $query): Builder
    {
        return SSAGoalScope::orderForList($query, $this);
    }
}
