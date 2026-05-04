<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

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

    /**
     * @return array{0: User, 1: User}
     */
    private function seedTherapistWithEligibleStudent(): array
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $service = Service::factory()->create();

        $therapist->students()->attach($student->id, [
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        return [$therapist, $student];
    }

    private function makePendingRequest(): QGlobRequest
    {
        [$therapist, $student] = $this->seedTherapistWithEligibleStudent();

        return QGlobRequest::factory()->create([
            'requested_by_id' => $therapist->id,
            'student_id' => $student->id,
            'status' => QGlobRequestStatus::PENDING,
        ]);
    }

    public function test_admin_can_approve(): void
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
    }

    public function test_admin_can_reject(): void
    {
        $admin = User::factory()->admin()->create();
        $request = $this->makePendingRequest();

        $respond = $this->actingAs($admin)->post(
            route('admin.qglob-requests.respond', $request),
            [
                'decision' => QGlobRequestStatus::REJECTED->value,
                'admin_response' => 'Not eligible at this time',
                '_token' => csrf_token(),
            ]
        );

        $respond->assertRedirect(route('admin.qglob-requests.show', $request));
        $request->refresh();
        self::assertSame(QGlobRequestStatus::REJECTED, $request->status);
        self::assertSame('Not eligible at this time', $request->admin_response);
    }

    public function test_admin_cannot_respond_to_non_pending_request(): void
    {
        $admin = User::factory()->admin()->create();
        $request = $this->makePendingRequest();
        $request->update(['status' => QGlobRequestStatus::APPROVED]);

        $respond = $this->actingAs($admin)->post(
            route('admin.qglob-requests.respond', $request),
            [
                'decision' => QGlobRequestStatus::REJECTED->value,
                '_token' => csrf_token(),
            ]
        );

        $respond->assertForbidden();
        $request->refresh();
        self::assertSame(QGlobRequestStatus::APPROVED, $request->status);
    }

    public function test_admin_show_loads(): void
    {
        $admin = User::factory()->admin()->create();
        $request = $this->makePendingRequest();

        $response = $this->actingAs($admin)->get(route('admin.qglob-requests.show', $request));
        $response->assertOk();
        $response->assertViewIs('admin.qglob-requests.show');
    }

    public function test_admin_index_loads(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.qglob-requests.index'));
        $response->assertOk();
        $response->assertViewIs('admin.qglob-requests.index');
    }
}
