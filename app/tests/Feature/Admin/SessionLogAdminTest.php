<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_session_logs_index(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.session-logs.index'));

        $response->assertOk();
        $response->assertSee('Session Logs');
    }
}
