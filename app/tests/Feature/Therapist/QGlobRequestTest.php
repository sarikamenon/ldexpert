<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

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

    private function seedEligibleStudent(User $therapist, User $student): void
    {
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
    }

    public function test_therapist_can_submit_and_view_qglob_request(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create(['user_id' => $student->id]);
        $this->seedEligibleStudent($therapist, $student);

        $index = $this->actingAs($therapist)->get(route('therapist.qglob-requests.index'));
        $index->assertOk();
        $index->assertViewIs('therapist.qglob-requests.index');

        $create = $this->actingAs($therapist)->get(route('therapist.qglob-requests.create'));
        $create->assertOk();
        $create->assertSee($student->name, false);

        $store = $this->actingAs($therapist)->post(route('therapist.qglob-requests.store'), [
            'student_id' => $student->id,
            'requested_date' => now()->addDay()->format('Y-m-d'),
            'requested_time' => '09:30',
            'note' => 'Evaluation session',
            '_token' => csrf_token(),
        ]);

        $store->assertRedirect(route('therapist.qglob-requests.index'));
        $store->assertSessionHasNoErrors();

        /** @var QGlobRequest $req */
        $req = QGlobRequest::query()->where('requested_by_id', $therapist->id)->firstOrFail();
        self::assertSame(QGlobRequestStatus::PENDING, $req->status);

        $show = $this->actingAs($therapist)->get(route('therapist.qglob-requests.show', $req));
        $show->assertOk();
        $show->assertSee('Evaluation session', false);
    }

    public function test_store_rejects_ineligible_student(): void
    {
        $therapist = User::factory()->therapist()->create();
        $otherStudent = User::factory()->student()->create();
        StudentProfile::factory()->create(['user_id' => $otherStudent->id]);

        $response = $this->actingAs($therapist)->post(route('therapist.qglob-requests.store'), [
            'student_id' => $otherStudent->id,
            'requested_date' => now()->addDay()->format('Y-m-d'),
            'requested_time' => '09:30',
            '_token' => csrf_token(),
        ]);

        $response->assertSessionHasErrors('student_id');
        self::assertSame(0, QGlobRequest::query()->count());
    }
}
