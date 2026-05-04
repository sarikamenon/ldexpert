<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create([
            'password_change_prompted_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_therapist_is_redirected_to_therapist_dashboard(): void
    {
        $therapist = User::factory()->therapist()->create([
            'password_change_prompted_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($therapist)->get('/dashboard');

        $response->assertRedirect(route('therapist.dashboard'));
    }

    public function test_user_with_null_password_change_prompted_at_redirected_to_password_edit(): void
    {
        $admin = User::factory()->admin()->create([
            'password_change_prompted_at' => null,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('password.edit'));
    }

    public function test_user_with_set_password_change_prompted_at_not_redirected_to_password_edit(): void
    {
        $admin = User::factory()->admin()->create([
            'password_change_prompted_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard'));
    }
}
