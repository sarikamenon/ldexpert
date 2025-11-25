<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
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
            'role' => Role::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the therapist profile for the user.
     */
    public function therapistProfile(): HasOne
    {
        return $this->hasOne(TherapistProfile::class);
    }

    /**
     * Get the student profile for the user.
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Get the parent profile for the user.
     */
    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /**
     * Get the admin profile for the user.
     */
    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    /**
     * Get the profile based on user role.
     */
    public function getProfileAttribute()
    {
        return match ($this->role) {
            Role::THERAPIST => $this->therapistProfile,
            Role::STUDENT => $this->studentProfile,
            Role::PARENT => $this->parentProfile,
            Role::ADMIN => $this->adminProfile,
            default => null,
        };
    }

    /**
     * Get the students for a therapist.
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
     */
    public function children(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'parent_id');
    }

    /**
     * Get the SSAs assigned to this therapist.
     */
    public function assignedSSAs(): HasMany
    {
        return $this->hasMany(ServiceSupportAgreement::class, 'assigned_therapist_id');
    }
}
