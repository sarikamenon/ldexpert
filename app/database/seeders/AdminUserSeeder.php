<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = 'Password123!';

        $admins = [
            [
                'email' => 'develop.ldexpert@gmail.com',
                'name' => 'System Admin',
            ],
            [
                'email' => 'info@ldexpert.org',
                'name' => 'Stephanie',
            ],
            [
                'email' => 'relatedservices@ldexpert.org',
                'name' => 'Chelsea',
            ],
        ];

        foreach ($admins as $admin) {
            $user = User::withTrashed()->firstOrNew(['email' => $admin['email']]);
            $user->name = $admin['name'];
            if (Schema::hasColumn('users', 'username')) {
                $user->username = $user->username ?: $admin['email'];
            }
            $user->role = Role::ADMIN->value;
            $user->status = UserStatus::ACTIVE->value;
            $user->password = Hash::make($password);
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->deleted_at = null;
            $user->save();

            AdminProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department' => 'Administration',
                    'phone' => null,
                ]
            );
        }
    }
}
