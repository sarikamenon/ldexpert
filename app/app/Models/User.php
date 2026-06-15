<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Concerns\HasAudits;
use App\Observers\UserObserver;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $username
 * @property Role $role
 * @property UserStatus $status
 */
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasAudits;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /** @var array<int, string> */
    protected array $auditIgnoreFields = [
        'password_change_prompted_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'status',
        'timezone',
        'password_change_prompted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_change_prompted_at' => 'datetime',
            'role' => Role::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Return username as the key for password reset tokens.
     * The password_reset_tokens.email column stores this value.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->username;
    }

    /** @param Builder<User> $query */
    public function scopeByRole(Builder $query, Role $role): void
    {
        $query->where('role', $role);
    }

    /**
     * Therapists eligible to sub for a session held by $excludeTherapistId on
     * $date for $serviceId, who share $positionId. Encapsulates the
     * "same position + active contract covering this service on this date" rule.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeEligibleAsSubFor(
        Builder $query,
        int $excludeTherapistId,
        int $positionId,
        int $serviceId,
        string $date,
    ): Builder {
        return $query
            ->where('users.id', '!=', $excludeTherapistId)
            ->whereHas('therapistProfile', function (Builder $q) use ($positionId): void {
                $q->forPosition($positionId); // @phpstan-ignore method.notFound
            })
            ->whereHas('therapistProfile.contracts', function (Builder $q) use ($date, $serviceId): void {
                $q->active() // @phpstan-ignore method.notFound
                    ->coveringDate($date)
                    ->forService($serviceId);
            });
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isTherapist(): bool
    {
        return $this->role === Role::THERAPIST;
    }

    /**
     * Get the therapist profile for the user.
     *
     * @return HasOne<TherapistProfile, $this>
     */
    public function therapistProfile(): HasOne
    {
        return $this->hasOne(TherapistProfile::class);
    }

    /**
     * Get the student profile for the user.
     *
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Get the parent profile for the user.
     *
     * @return HasOne<ParentProfile, $this>
     */
    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /**
     * Get the admin profile for the user.
     *
     * @return HasOne<AdminProfile, $this>
     */
    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    /**
     * Get the profile based on user role.
     */
    public function getProfileAttribute(): TherapistProfile|StudentProfile|ParentProfile|AdminProfile|null
    {
        return match ($this->role) {
            Role::THERAPIST => $this->therapistProfile,
            Role::STUDENT => $this->studentProfile,
            Role::PARENT => $this->parentProfile,
            Role::ADMIN => $this->adminProfile,
        };
    }

    /**
     * Get the students for a therapist.
     *
     * @return BelongsToMany<User, $this, TherapistStudent, 'pivot'>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'therapist_student',
            'therapist_id',
            'student_id'
        )->using(TherapistStudent::class)
            ->withPivot('assigned_at', 'status')
            ->withTimestamps();
    }

    /**
     * Get the therapists for a student.
     *
     * @return BelongsToMany<User, $this, TherapistStudent, 'pivot'>
     */
    public function therapists(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'therapist_student',
            'student_id',
            'therapist_id'
        )->using(TherapistStudent::class)
            ->withPivot('assigned_at', 'status')
            ->withTimestamps();
    }

    /**
     * Get the children (students) for a parent.
     *
     * @return HasMany<StudentProfile, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'parent_id');
    }

    /**
     * Get the SSAs assigned to this therapist.
     *
     * @return HasMany<ServiceSupportAgreement, $this>
     */
    public function assignedSSAs(): HasMany
    {
        return $this->hasMany(ServiceSupportAgreement::class, 'assigned_therapist_id');
    }

    /**
     * Get documents attached to this user (when user is a student).
     *
     * @return MorphMany<\App\Models\StudentDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(\App\Models\StudentDocument::class, 'documentable');
    }

    /**
     * Get therapist bills for this user when the user is a therapist.
     *
     * @return HasMany<TherapistBill, $this>
     */
    public function therapistBills(): HasMany
    {
        return $this->hasMany(TherapistBill::class, 'therapist_id');
    }

    /**
     * Get ledger entries associated with this user as a ledgerable entity.
     *
     * @return MorphMany<\App\Models\LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'ledgerable');
    }
}
