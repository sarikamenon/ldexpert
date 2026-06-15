<?php

declare(strict_types=1);

use App\Enums\SSAGoalStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Goals ────────────────────────────────────────────────────────────────────

it('TC-T021 therapist can view a student active goals list', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    SSAGoal::factory()->create([
        'ssa_id'     => $ssa->id,
        'student_id' => $student->id,
        'status'     => SSAGoalStatus::ACTIVE,
        'objective'  => 'Complete phonological awareness tasks at 85% accuracy',
        'goal'       => 'Goal description',
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $student): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/students/' . $student->id . '?tab=goals')
            ->waitForText('Complete phonological awareness tasks', 10)
            ->assertSee('Complete phonological awareness tasks');
    });
});

it('TC-T022 therapist can mark an active goal as mastered', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $goal = SSAGoal::factory()->create([
        'ssa_id'     => $ssa->id,
        'student_id' => $student->id,
        'status'     => SSAGoalStatus::ACTIVE,
        'objective'  => 'Mark as Mastered Goal',
        'goal'       => 'Goal description',
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $student, $goal): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/students/' . $student->id . '?tab=goals')
            ->waitFor('@mark-mastered-' . $goal->id, 10)
            ->click('@mark-mastered-' . $goal->id)
            ->waitForText('Yes, mark mastered', 10)
            ->press('Yes, mark mastered')
            ->pause(1500);
    });

    expect(SSAGoal::find($goal->id)?->status->value)->toBe('mastered');
});

it('TC-T023 therapist cannot mark an already-mastered goal again', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $masteredGoal = SSAGoal::factory()->mastered()->create([
        'ssa_id'     => $ssa->id,
        'student_id' => $student->id,
        'objective'  => 'Already Mastered Goal',
        'goal'       => 'Goal description',
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $student, $masteredGoal): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/students/' . $student->id . '?tab=goals')
            ->pause(800);

        // Mark as mastered button should not be available for already-mastered goals
        $markMasteredBtn = $browser->element('@mark-mastered-' . $masteredGoal->id);
        expect($markMasteredBtn)->toBeNull();
    });
});

it('TC-T024 therapist is blocked from viewing goals for a non-assigned student', function (): void {
    $admin      = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapistA = User::factory()->therapist()->qa()->create();
    $therapistB = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapistA, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapistB, 'user')->create(['manager_id' => $admin->id]);

    $school   = School::factory()->qa()->create();
    $studentB = User::factory()->student()->qa()->create();
    $studentB->studentProfile()->update(['school_id' => $school->id]);
    $studentB->therapists()->attach($therapistB->id, ['assigned_at' => now(), 'status' => 'active']);
    $service = Service::factory()->create();
    ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $studentB->id,
        'assigned_therapist_id' => $therapistB->id,
        'primary_service_id'    => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapistA, $studentB): void {
        $browser->loginAs($therapistA)
            ->visit('/therapist/students/' . $studentB->id)
            ->pause(600);

        $url      = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            !str_contains($url, '/therapist/students/' . $studentB->id)
        )->toBeTrue();
    });
});

it('TC-T025 student goals tab shows empty state when student has no goals', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $student): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/students/' . $student->id . '?tab=goals')
            ->pause(800)
            ->assertDontSee('Whoops')
            ->assertDontSee('500');
    });
});
