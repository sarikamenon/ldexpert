<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Constants\EvaluationServiceNames;
use App\Enums\QGlobRequestStatus;
use App\Enums\SSAStatus;
use App\Models\QGlobRequest;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QGlobRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingRequest(): QGlobRequest
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $evalService = Service::factory()->create([
            'name' => EvaluationServiceNames::all()[0],
        ]);

        $therapist->students()->attach($student->id, [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $evalService->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        return QGlobRequest::factory()->create([
            'requested_by_id' => $therapist->id,
            'student_id' => $student->id,
            'status' => QGlobRequestStatus::PENDING,
        ]);
    }

    public function test_admin_can_approve_and_complete(): void
    {
        $admin = User::factory()->admin()->create();
        $request = $this->makePendingRequest();

        $respond = $this->actingAs($admin)->post(
            route('admin.qglob-requests.respond', $request),
            [
                'decision' => QGlobRequestStatus::APPROVED->value,
                'admin_response' => 'Approved for slot',
                '_token' => csrf_token(),
            ]
        );

        $respond->assertRedirect(route('admin.qglob-requests.show', $request));
        $request->refresh();
        self::assertSame(QGlobRequestStatus::APPROVED, $request->status);
        self::assertSame('Approved for slot', $request->admin_response);
        self::assertSame($admin->id, (int) $request->responded_by_id);

        $complete = $this->actingAs($admin)->post(
            route('admin.qglob-requests.complete', $request),
            ['_token' => csrf_token()]
        );

        $complete->assertRedirect(route('admin.qglob-requests.show', $request));
        $request->refresh();
        self::assertSame(QGlobRequestStatus::COMPLETED, $request->status);
    }

    public function test_admin_index_loads(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.qglob-requests.index'));
        $response->assertOk();
        $response->assertViewIs('admin.qglob-requests.index');
    }
}
