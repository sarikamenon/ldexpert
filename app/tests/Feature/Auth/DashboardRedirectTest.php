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
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_therapist_is_redirected_to_therapist_dashboard(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)->get('/dashboard');

        $response->assertRedirect(route('therapist.dashboard'));
    }
}
