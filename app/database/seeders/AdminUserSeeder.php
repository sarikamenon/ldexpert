<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'admin@example.com';
        $password = 'Password123!';
        $name = 'System Admin';

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->role = Role::ADMIN->value;
        $user->status = UserStatus::ACTIVE->value;
        $user->password = Hash::make($password);
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->deleted_at = null;
        $user->save();

        AdminProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'department' => 'Operations',
                'phone' => null,
            ]
        );
    }
}
