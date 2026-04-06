<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SessionLog;
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
        $response->assertSee('School/Family Amount');
        $response->assertSee('Therapist Amount');
        $response->assertSee('Duration');
    }

    public function test_admin_session_logs_index_applies_sent_back_status_from_query(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.session-logs.index', ['status' => 'sent_back']));

        $response->assertOk();
        $response->assertViewHas('filters', static function (array $filters): bool {
            return ($filters['status'] ?? null) === 'sent_back';
        });
    }

    public function test_admin_session_logs_data_filters_by_sent_back_status(): void
    {
        $admin = User::factory()->admin()->create();

        SessionLog::factory()->sentBack()->create();
        SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.session-logs.data'), [
            '_token' => csrf_token(),
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'filter_school_id' => '',
            'filter_student_id' => '',
            'filter_therapist_id' => '',
            'filter_service_id' => '',
            'filter_ssa_id' => '',
            'filter_status' => 'sent_back',
            'filter_date_from' => '',
            'filter_date_to' => '',
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertCount(1, $response->json('data'));
    }
}
