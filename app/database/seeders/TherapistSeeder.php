<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TherapistSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('THERAPIST_EMAIL', 'therapist@example.com');
        $password = env('THERAPIST_PASSWORD', 'Temp1234!');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => env('THERAPIST_NAME', 'Default Therapist'),
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'therapist',
            ]);

            TherapistProfile::query()->create([
                'user_id' => $user->id,
            ]);
        }
    }
}
