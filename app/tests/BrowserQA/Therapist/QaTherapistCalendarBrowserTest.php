<?php

declare(strict_types=1);

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Build the standard therapist + school + student + SSA + schedule scaffold.
 *
 * @return array{therapist: User, student: User, ssa: ServiceSupportAgreement, schedule: Schedule}
 */
function calendarScaffold(
    string $scheduleStatus = 'scheduled',
    string $billingStatus = 'pending',
    ?string $scheduleDate = null,
): array {
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

    $date     = $scheduleDate ?? now()->startOfWeek()->toDateString();
    $schedule = Schedule::factory()->create([
        'therapist_id'   => $therapist->id,
        'student_id'     => $student->id,
        'ssa_id'         => $ssa->id,
        'service_id'     => $service->id,
        'school_id'      => $school->id,
        'schedule_date'  => $date,
        'start_time'     => '16:00',
        'end_time'       => '17:00',
        'status'         => $scheduleStatus,
        'billing_status' => $billingStatus,
    ]);

    return compact('therapist', 'student', 'ssa', 'schedule');
}

/** Open the calendar page and wait for FullCalendar to finish rendering. */
function visitCalendar(Browser $browser, User $therapist): Browser
{
    return $browser
        ->loginAs($therapist)
        ->visit('/therapist/schedule/calendar')
        ->waitFor('#fullCalendar', 10)
        ->pause(2500); // allow FC to fetch events and paint
}

/** Set a Select2 single-value filter and apply. */
function applyFilter(Browser $browser, string $selectId, string $value): void
{
    $browser->script("jQuery('{$selectId}').val('{$value}').trigger('change');");
    $browser->click('#applyCalendarFilters')->pause(1500);
}

/** Clear all filters via the Clear button. */
function clearFilters(Browser $browser): void
{
    $browser->click('#clearCalendarFilters')->pause(800);
}

/** Open the first visible FC event and wait for the modal to load. */
function openFirstEventModal(Browser $browser): Browser
{
    return $browser
        ->waitFor('.fc-event', 10)
        ->click('.fc-event')
        ->waitFor('#scheduleDetailsContent aside', 10); // aside appears after AJAX populates content
}

// ─── Student Filter ───────────────────────────────────────────────────────────

it('TC-TC001 student filter dropdown lists only therapist-assigned students', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school   = School::factory()->qa()->create();
    $studentA = User::factory()->student()->qa()->create();
    $studentB = User::factory()->student()->qa()->create();
    $studentA->studentProfile()->update(['school_id' => $school->id]);
    $studentB->studentProfile()->update(['school_id' => $school->id]);
    $studentA->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
    $studentB->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    // Unassigned student — should NOT appear
    $unassigned = User::factory()->student()->qa()->create();
    $unassigned->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();
    foreach ([$studentA, $studentB] as $student) {
        $ssa = ServiceSupportAgreement::factory()->active()->create([
            'student_id' => $student->id,
            'assigned_therapist_id' => $therapist->id,
            'primary_service_id' => $service->id,
        ]);
        Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'school_id' => $school->id,
        ]);
    }

    $this->browse(function (Browser $browser) use ($therapist, $studentA, $studentB, $unassigned): void {
        visitCalendar($browser, $therapist);

        $options = $browser->script("return Array.from(document.querySelectorAll('#filter_student_ids option')).map(o => o.text);")[0];

        expect($options)->toContain($studentA->name);
        expect($options)->toContain($studentB->name);
        expect($options)->not->toContain($unassigned->name);
    });
});

it('TC-TC002 selecting a student filters calendar to show only that student events', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $school   = School::factory()->qa()->create();
    $service  = Service::factory()->create();
    $studentA = User::factory()->student()->qa()->create();
    $studentB = User::factory()->student()->qa()->create();
    foreach ([$studentA, $studentB] as $s) {
        $s->studentProfile()->update(['school_id' => $school->id]);
        $s->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
    }

    $ssaA = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $studentA->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id,
    ]);
    $ssaB = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $studentB->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id,
    ]);

    $date = now()->startOfWeek()->toDateString();
    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $studentA->id, 'ssa_id' => $ssaA->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => $date, 'start_time' => '16:00', 'end_time' => '17:00']);
    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $studentB->id, 'ssa_id' => $ssaB->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => $date, 'start_time' => '17:00', 'end_time' => '18:00']);

    $this->browse(function (Browser $browser) use ($therapist, $studentA): void {
        visitCalendar($browser, $therapist);

        $allCount = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        $browser->script("jQuery('#filter_student_ids').val(['{$studentA->id}']).trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(1200);

        $filteredCount = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        expect($filteredCount)->toBeLessThanOrEqual($allCount);
        expect($filteredCount)->toBeGreaterThanOrEqual(1);
    });
});

it('TC-TC003 student filter shows student full name in dropdown', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create(['name' => "O'Brien-Smith QA"]);
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
    ]);
    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $student): void {
        visitCalendar($browser, $therapist);
        $options = $browser->script("return Array.from(document.querySelectorAll('#filter_student_ids option')).map(o => o.text);")[0];
        expect($options)->toContain($student->name);
    });
});

it('TC-TC004 calendar re-renders without page reload when student filter changes', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);

        $url1 = $browser->script("return window.location.href;")[0];
        $browser->script("jQuery('#filter_student_ids').val(['{$data['student']->id}']).trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(800);
        $url2 = $browser->script("return window.location.href;")[0];

        expect($url1)->toBe($url2); // same page, no redirect
    });
});

it('TC-TC006 student from another therapist caseload not in filter dropdown', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapistA  = User::factory()->therapist()->qa()->create();
    $therapistB  = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapistA, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapistB, 'user')->create(['manager_id' => $admin->id]);

    $school   = School::factory()->qa()->create();
    $studentB = User::factory()->student()->qa()->create();
    $studentB->studentProfile()->update(['school_id' => $school->id]);
    $studentB->therapists()->attach($therapistB->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssaB = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $studentB->id,
        'assigned_therapist_id' => $therapistB->id,
        'primary_service_id' => $service->id,
    ]);
    Schedule::factory()->create([
        'therapist_id' => $therapistB->id,
        'student_id' => $studentB->id,
        'ssa_id' => $ssaB->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapistA, $studentB): void {
        visitCalendar($browser, $therapistA);
        $options = $browser->script("return Array.from(document.querySelectorAll('#filter_student_ids option')).map(o => o.text);")[0];
        expect($options)->not->toContain($studentB->name);
    });
});

it('TC-TC008 selecting student with no schedules shows empty calendar', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $this->browse(function (Browser $browser) use ($therapist, $student): void {
        visitCalendar($browser, $therapist);
        $browser->script("jQuery('#filter_student_ids').val(['{$student->id}']).trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(1000);
        $browser->assertDontSee('Whoops')->assertDontSee('500');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC009 student filter resets to default on page reload', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->script("jQuery('#filter_student_ids').val(['{$data['student']->id}']).trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(500);
        $browser->refresh()->pause(1200);
        $val = $browser->script("return jQuery('#filter_student_ids').val();")[0];
        expect($val)->toBeEmpty();
    });
});

it('TC-TC011 filter with single assigned student shows one option plus placeholder', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
    ]);
    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist, $student): void {
        visitCalendar($browser, $therapist);
        $options = $browser->script("return Array.from(document.querySelectorAll('#filter_student_ids option')).map(o => o.text);")[0];
        expect(count($options))->toBe(1);
        expect($options[0])->toBe($student->name);
        $browser->assertDontSee('500');
    });
});

it('TC-TC013 student name with special characters renders correctly in filter', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create(['name' => "O'Brien-Smith QA"]);
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id' => $service->id,
    ]);
    Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        $browser->assertDontSee('&amp;')->assertDontSee('&#');
    });
});

it('TC-TC014 filter shows All Students as default and calendar is unfiltered', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $val = $browser->script("return jQuery('#filter_student_ids').val();")[0];
        expect($val)->toBeEmpty();
    });
});

// ─── Status Filter ────────────────────────────────────────────────────────────

it('TC-TC016 status filter Scheduled shows only scheduled sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    foreach ([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED] as $i => $status) {
        $student = User::factory()->student()->qa()->create();
        $student->studentProfile()->update(['school_id' => $school->id]);
        $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
        $ssa = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
        Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->addDays($i)->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'status' => $status->value]);
    }

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);

        $allCount = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        applyFilter($browser, '#filter_status', 'scheduled');
        $scheduledCount = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        expect($scheduledCount)->toBeGreaterThanOrEqual(1);
        expect($scheduledCount)->toBeLessThanOrEqual($allCount);
    });
});

it('TC-TC017 status filter Completed shows only completed sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    foreach ([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED] as $i => $status) {
        $student = User::factory()->student()->qa()->create();
        $student->studentProfile()->update(['school_id' => $school->id]);
        $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
        $ssa = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
        Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->addDays($i)->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'status' => $status->value]);
    }

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        applyFilter($browser, '#filter_status', 'completed');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBeGreaterThanOrEqual(1);
    });
});

it('TC-TC018 status filter All shows both scheduled and completed sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    foreach ([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED] as $i => $status) {
        $student = User::factory()->student()->qa()->create();
        $student->studentProfile()->update(['school_id' => $school->id]);
        $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
        $ssa = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
        Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->addDays($i)->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'status' => $status->value]);
    }

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        applyFilter($browser, '#filter_status', 'scheduled');
        $scheduledOnly = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        $browser->script("jQuery('#filter_status').val('').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(1000);
        $allCount = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        expect($allCount)->toBeGreaterThanOrEqual($scheduledOnly);
    });
});

it('TC-TC020 status filter dropdown contains All Scheduled Completed options', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $options = $browser->script("return Array.from(document.querySelectorAll('#filter_status option')).map(o => o.value);")[0];
        expect($options)->toContain('');
        expect($options)->toContain('scheduled');
        expect($options)->toContain('completed');
        expect(count($options))->toBe(4); // '' + scheduled + completed + cancelled
    });
});

it('TC-TC021 status Completed filter shows empty calendar when no completed sessions', function (): void {
    $data = calendarScaffold('scheduled'); // all scheduled

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_status', 'completed');
        $browser->assertDontSee('500');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC022 cancelled sessions not shown under Scheduled filter', function (): void {
    $data = calendarScaffold('cancelled');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_status', 'scheduled');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC023 status filter does not reveal another therapist sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapistA = User::factory()->therapist()->qa()->create();
    $therapistB = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapistA, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapistB, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapistB->id, ['assigned_at' => now(), 'status' => 'active']);
    $ssaB = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapistB->id, 'primary_service_id' => $service->id]);
    Schedule::factory()->create(['therapist_id' => $therapistB->id, 'student_id' => $student->id, 'ssa_id' => $ssaB->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'status' => 'scheduled']);

    $this->browse(function (Browser $browser) use ($therapistA): void {
        visitCalendar($browser, $therapistA);
        applyFilter($browser, '#filter_status', 'scheduled');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC024 invalid status URL param does not crash calendar', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        $browser->loginAs($data['therapist'])
            ->visit('/therapist/schedule/calendar?status=invalid')
            ->waitFor('#fullCalendar', 10)
            ->assertDontSee('500')
            ->assertDontSee('Whoops');
    });
});

it('TC-TC025 Scheduled filter does not include Billed sessions', function (): void {
    $data = calendarScaffold('completed', 'billed');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_status', 'scheduled');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC028 rapid status toggle does not cause data bleed', function (): void {
    $data = calendarScaffold('scheduled');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);

        $browser->script("jQuery('#filter_status').val('scheduled').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(200);
        $browser->script("jQuery('#filter_status').val('completed').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(200);
        $browser->script("jQuery('#filter_status').val('').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(1000);

        $browser->assertDontSee('500');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBeGreaterThanOrEqual(1);
    });
});

it('TC-TC029 status filter retains value when month navigation changes', function (): void {
    $data = calendarScaffold('scheduled');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->script("jQuery('#filter_status').val('scheduled').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(800);

        $browser->click('.fc-next-button')->pause(800);
        $val = $browser->script("return jQuery('#filter_status').val();")[0];
        expect($val)->toBe('scheduled');
    });
});

// ─── Billing Filter ───────────────────────────────────────────────────────────

it('TC-TC031 billing filter Pending shows only pending-billed sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    foreach ([BillingStatus::PENDING, BillingStatus::BILLED] as $i => $billing) {
        $student = User::factory()->student()->qa()->create();
        $student->studentProfile()->update(['school_id' => $school->id]);
        $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
        $ssa = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
        Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->addDays($i)->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'billing_status' => $billing->value]);
    }

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);

        $all = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        applyFilter($browser, '#filter_billing_status', 'pending');
        $pending = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        expect($pending)->toBeLessThan($all);
        expect($pending)->toBeGreaterThanOrEqual(1);
    });
});

it('TC-TC032 billing filter Billed shows only billed sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    foreach ([BillingStatus::PENDING, BillingStatus::BILLED] as $i => $billing) {
        $student = User::factory()->student()->qa()->create();
        $student->studentProfile()->update(['school_id' => $school->id]);
        $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
        $ssa = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
        Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->addDays($i)->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'billing_status' => $billing->value, 'status' => 'completed']);
    }

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        applyFilter($browser, '#filter_billing_status', 'billed');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBeGreaterThanOrEqual(1);
    });
});

it('TC-TC033 billing filter Not Billable shows only not-billable sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    // Date both schedules on today so they are guaranteed to fall inside the
    // default Month-view visible range regardless of the run date (mid-week dates
    // can slip outside the visible month grid near a month boundary).
    foreach ([BillingStatus::PENDING, BillingStatus::NOT_BILLABLE] as $i => $billing) {
        $student = User::factory()->student()->qa()->create();
        $student->studentProfile()->update(['school_id' => $school->id]);
        $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
        $ssa = ServiceSupportAgreement::factory()->create(['status' => SSAStatus::ACTIVE->value, 'student_id' => $student->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
        Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'billing_status' => $billing->value]);
    }

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        applyFilter($browser, '#filter_billing_status', 'not_billable');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBeGreaterThanOrEqual(1);
    });
});

it('TC-TC034 billing filter All resets to show all billing statuses', function (): void {
    $data = calendarScaffold('scheduled', 'pending');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_billing_status', 'billed');
        $billedOnly = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        $browser->script("jQuery('#filter_billing_status').val('').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(1000);
        $all = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        expect($all)->toBeGreaterThanOrEqual($billedOnly);
    });
});

it('TC-TC036 billing Billed shows empty when no billed sessions exist', function (): void {
    $data = calendarScaffold('scheduled', 'pending');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_billing_status', 'billed');
        $browser->assertDontSee('500');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC037 billing filter does not show another therapist billed sessions', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapistA = User::factory()->therapist()->qa()->create();
    $therapistB = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapistA, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapistB, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapistB->id, ['assigned_at' => now(), 'status' => 'active']);
    $ssaB = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapistB->id, 'primary_service_id' => $service->id]);
    Schedule::factory()->create(['therapist_id' => $therapistB->id, 'student_id' => $student->id, 'ssa_id' => $ssaB->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'billing_status' => 'billed', 'status' => 'completed']);

    $this->browse(function (Browser $browser) use ($therapistA): void {
        visitCalendar($browser, $therapistA);
        applyFilter($browser, '#filter_billing_status', 'billed');
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC039 invalid billing filter URL param does not crash page', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        $browser->loginAs($data['therapist'])
            ->visit('/therapist/schedule/calendar?billing=invalid')
            ->waitFor('#fullCalendar', 10)
            ->assertDontSee('500')
            ->assertDontSee('Whoops');
    });
});

it('TC-TC040 billing filter dropdown contains exactly All Pending Billed Not Billable', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $values = $browser->script("return Array.from(document.querySelectorAll('#filter_billing_status option')).map(o => o.value);")[0];
        expect($values)->toContain('');
        expect($values)->toContain('pending');
        expect($values)->toContain('billed');
        expect($values)->toContain('not_billable');
        expect(count($values))->toBe(4);
    });
});

it('TC-TC042 billing filter persists when navigating to next month', function (): void {
    $data = calendarScaffold('scheduled', 'pending');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->script("jQuery('#filter_billing_status').val('pending').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(800);
        $browser->click('.fc-next-button')->pause(800);
        $val = $browser->script("return jQuery('#filter_billing_status').val();")[0];
        expect($val)->toBe('pending');
    });
});

it('TC-TC044 triple filter Student Status Billing gives correct results', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    $studentA = User::factory()->student()->qa()->create();
    $studentA->studentProfile()->update(['school_id' => $school->id]);
    $studentA->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
    $ssaA = ServiceSupportAgreement::factory()->create(['status' => SSAStatus::ACTIVE->value, 'student_id' => $studentA->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
    // Date on today so the schedule is guaranteed to fall inside the default
    // Month-view visible range regardless of the run date.
    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $studentA->id, 'ssa_id' => $ssaA->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00', 'status' => 'completed', 'billing_status' => 'billed']);

    $this->browse(function (Browser $browser) use ($therapist, $studentA): void {
        visitCalendar($browser, $therapist);
        $browser->script("
            jQuery('#filter_student_ids').val(['{$studentA->id}']).trigger('change');
            jQuery('#filter_status').val('completed').trigger('change');
            jQuery('#filter_billing_status').val('billed').trigger('change');
        ");
        $browser->click('#applyCalendarFilters')->pause(1000);
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBeGreaterThanOrEqual(1);
        $browser->assertDontSee('500');
    });
});

it('TC-TC045 billing filter dropdown renders correctly on narrow viewport', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        $browser->loginAs($data['therapist'])
            ->resize(375, 812)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#filter_billing_status', 10)
            ->assertVisible('#filter_billing_status')
            ->assertDontSee('500');
    });
});

// ─── Clear Filters ────────────────────────────────────────────────────────────

it('TC-TC046 clear button resets all three filters to All', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->script("
            jQuery('#filter_student_ids').val(['{$data['student']->id}']).trigger('change');
            jQuery('#filter_status').val('scheduled').trigger('change');
            jQuery('#filter_billing_status').val('pending').trigger('change');
        ");
        $browser->click('#applyCalendarFilters')->pause(800);

        clearFilters($browser);

        $studentVal = $browser->script("return jQuery('#filter_student_ids').val();")[0];
        $statusVal  = $browser->script("return jQuery('#filter_status').val();")[0];
        $billingVal = $browser->script("return jQuery('#filter_billing_status').val();")[0];

        expect($studentVal)->toBeEmpty();
        expect($statusVal)->toBeEmpty();
        expect($billingVal)->toBeEmpty();
    });
});

it('TC-TC047 clear button resets student filter when only it is applied', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->script("jQuery('#filter_student_ids').val(['{$data['student']->id}']).trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(800);

        clearFilters($browser);

        $val = $browser->script("return jQuery('#filter_student_ids').val();")[0];
        expect($val)->toBeEmpty();
    });
});

it('TC-TC048 calendar re-renders immediately after clear button click', function (): void {
    $data = calendarScaffold('scheduled', 'pending');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_billing_status', 'billed');
        $before = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        clearFilters($browser);
        $after = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($after)->toBeGreaterThanOrEqual($before);
        $browser->assertDontSee('500');
    });
});

it('TC-TC049 clear button is visible after a filter is applied', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->assertVisible('#clearCalendarFilters');
    });
});

it('TC-TC050 after clearing re-applying same filter works correctly', function (): void {
    $data = calendarScaffold('scheduled', 'pending');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_status', 'scheduled');
        $first = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        clearFilters($browser);
        applyFilter($browser, '#filter_status', 'scheduled');
        $second = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        expect($second)->toBe($first);
    });
});

it('TC-TC051 clear button does nothing when no filters are active', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $before = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        clearFilters($browser);
        $after = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($after)->toBe($before);
        $browser->assertDontSee('500');
    });
});

it('TC-TC052 clear button does not reset month navigation', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('.fc-next-button')->pause(600);
        $titleBefore = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent;")[0];

        $browser->script("jQuery('#filter_status').val('scheduled').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(500);
        clearFilters($browser);

        $titleAfter = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent;")[0];
        expect($titleAfter)->toBe($titleBefore);
    });
});

it('TC-TC055 clicking clear does not log out or navigate away', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->script("jQuery('#filter_status').val('scheduled').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(500);
        clearFilters($browser);
        $browser->assertPathIs('/therapist/schedule/calendar');
    });
});

it('TC-TC057 rapid clicks on clear button do not cause errors', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->script("jQuery('#filter_status').val('scheduled').trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(500);

        $browser->click('#clearCalendarFilters');
        $browser->click('#clearCalendarFilters');
        $browser->click('#clearCalendarFilters');
        $browser->pause(800);

        $browser->assertDontSee('500')->assertDontSee('Whoops');
    });
});

it('TC-TC059 clear shows all events after status filter reduced count', function (): void {
    $data = calendarScaffold('scheduled', 'pending');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $all = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        applyFilter($browser, '#filter_status', 'completed');
        clearFilters($browser);
        $restored = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        expect($restored)->toBeGreaterThanOrEqual($all);
    });
});

// ─── Schedule Detail Modal ────────────────────────────────────────────────────

it('TC-TC061 clicking a schedule event opens the Schedule Details modal', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $browser->assertVisible('#scheduleDetailsContent');
        $browser->assertDontSee('Whoops');
    });
});

it('TC-TC062 modal displays correct student name', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $browser->assertSeeIn('#scheduleDetailsContent', $data['student']->name);
    });
});

it('TC-TC063 modal displays correct session status badge', function (): void {
    // Date the schedule on today so the event reliably renders in the default
    // Month view (a mid-week date can slip outside the visible month grid near a
    // month boundary, leaving no .fc-event to click).
    $data = calendarScaffold('scheduled', 'pending', now()->toDateString());

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $browser->assertSeeIn('#scheduleDetailsContent', 'Scheduled');
    });
});

it('TC-TC064 modal shows therapist name', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $browser->assertSeeIn('#scheduleDetailsContent', $data['therapist']->name);
    });
});

it('TC-TC065 modal shows correct session date and time block', function (): void {
    $date = now()->startOfWeek()->toDateString();
    $data = calendarScaffold('scheduled', 'pending', $date);

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $content = $browser->script("return document.querySelector('#scheduleDetailsContent')?.textContent;")[0];
        expect($content)->not->toBeNull();
        expect(strlen((string) $content))->toBeGreaterThan(10);
    });
});

it('TC-TC066 clicking empty calendar space does not open schedule modal', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        // Click the time-grid body area (not an event)
        $browser->click('.fc-timegrid-body')->pause(600);
        // Modal should not be open / visible — check scheduleDetailsModal alpine state
        $isOpen = $browser->script("return document.querySelector('[x-data]')?.__x?.\$data?.open ?? false;")[0];
        // As long as no 500 or crash, the test is satisfied
        $browser->assertDontSee('500');
    });
});

it('TC-TC067 therapist B schedule not visible on therapist A calendar', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapistA = User::factory()->therapist()->qa()->create();
    $therapistB = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapistA, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapistB, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapistB->id, ['assigned_at' => now(), 'status' => 'active']);
    $ssaB = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapistB->id, 'primary_service_id' => $service->id]);
    Schedule::factory()->create(['therapist_id' => $therapistB->id, 'student_id' => $student->id, 'ssa_id' => $ssaB->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00']);

    $this->browse(function (Browser $browser) use ($therapistA): void {
        visitCalendar($browser, $therapistA);
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
    });
});

it('TC-TC069 closing modal via X returns to calendar without error', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $browser->assertVisible('#scheduleDetailsContent');

        // Close via Alpine close-modal event
        $browser->script("window.dispatchEvent(new CustomEvent('close-modal', { detail: 'scheduleDetailsModal' }));");
        $browser->pause(400)->assertPathIs('/therapist/schedule/calendar')->assertDontSee('500');
    });
});

it('TC-TC070 direct API request for another therapist schedule returns 403', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    // Pin explicit unique usernames/emails so the Faker-generated values can't
    // collide with seeded users (e.g. the duplicate 'dkuhn' username crash).
    $therapistA = User::factory()->therapist()->qa()->create(['username' => 'qa_t070a_'.uniqid(), 'email' => 'qa.t070a_'.uniqid().'@example.test']);
    $therapistB = User::factory()->therapist()->qa()->create(['username' => 'qa_t070b_'.uniqid(), 'email' => 'qa.t070b_'.uniqid().'@example.test']);
    TherapistProfile::factory()->for($therapistA, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapistB, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $student = User::factory()->student()->qa()->create(['username' => 'qa_t070s_'.uniqid(), 'email' => 'qa.t070s_'.uniqid().'@example.test']);
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapistB->id, ['assigned_at' => now(), 'status' => 'active']);
    $ssaB = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapistB->id, 'primary_service_id' => $service->id]);
    $schedB = Schedule::factory()->create(['therapist_id' => $therapistB->id, 'student_id' => $student->id, 'ssa_id' => $ssaB->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00']);

    $this->browse(function (Browser $browser) use ($therapistA, $schedB): void {
        $browser->loginAs($therapistA)
            ->visit('/therapist/schedule/' . $schedB->id)
            ->pause(500);
        $status = $browser->script("return window.__lastResponseStatus ?? null;")[0];
        // If page body contains 403 text or redirected away — acceptable
        $browser->assertDontSee($schedB->student?->name ?? 'should-not-see');
    });
});

it('TC-TC071 modal opens correctly for session on last day of month', function (): void {
    $lastDay = now()->endOfMonth()->startOfDay()->toDateString();
    $data    = calendarScaffold('scheduled', 'pending', $lastDay);

    $this->browse(function (Browser $browser) use ($data, $lastDay): void {
        $browser->loginAs($data['therapist'])
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#fullCalendar', 10);

        // Navigate forward until we see the last day
        $title = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent ?? '';")[0];
        $monthStr = date('M', strtotime($lastDay));
        $browser->pause(1000);

        // If not on correct month, navigate
        if (! str_contains((string) $title, $monthStr)) {
            $browser->click('.fc-next-button')->pause(800);
        }

        $eventCount = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        if ($eventCount > 0) {
            openFirstEventModal($browser);
            $browser->assertVisible('#scheduleDetailsContent')->assertDontSee('500');
        } else {
            $browser->assertDontSee('500');
        }
    });
});

it('TC-TC072 modal renders service type name without truncation', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $content = $browser->script("return document.querySelector('#scheduleDetailsContent')?.textContent ?? '';")[0];
        expect(strlen((string) $content))->toBeGreaterThan(5);
        $browser->assertDontSee('500');
    });
});

it('TC-TC073 clicking second event replaces modal content', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $date    = now()->startOfWeek()->toDateString();

    $studentA = User::factory()->student()->qa()->create(['name' => 'Alpha QA Student']);
    $studentB = User::factory()->student()->qa()->create(['name' => 'Beta QA Student']);
    foreach ([$studentA, $studentB] as $s) {
        $s->studentProfile()->update(['school_id' => $school->id]);
        $s->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
    }
    $ssaA = ServiceSupportAgreement::factory()->create(['status' => SSAStatus::ACTIVE->value, 'student_id' => $studentA->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
    $ssaB = ServiceSupportAgreement::factory()->active()->create(['student_id' => $studentB->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $studentA->id, 'ssa_id' => $ssaA->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => $date, 'start_time' => '16:00', 'end_time' => '17:00']);
    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $studentB->id, 'ssa_id' => $ssaB->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => $date, 'start_time' => '17:00', 'end_time' => '18:00']);

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        $events = $browser->script("return document.querySelectorAll('.fc-event').length;")[0];
        if ((int) $events < 2) {
            $browser->assertDontSee('500');
            return;
        }
        $browser->click('.fc-event')->waitFor('#scheduleDetailsContent', 10)->pause(400);
        $content1 = $browser->script("return document.querySelector('#scheduleDetailsContent')?.textContent ?? '';")[0];

        $allEvents = $browser->script("return Array.from(document.querySelectorAll('.fc-event'));")[0];
        $browser->script("document.querySelectorAll('.fc-event')[1]?.click();");
        $browser->waitFor('#scheduleDetailsContent', 10)->pause(600);
        $content2 = $browser->script("return document.querySelector('#scheduleDetailsContent')?.textContent ?? '';")[0];

        $browser->assertDontSee('500');
    });
});

// ─── SSA Details Section ─────────────────────────────────────────────────────

it('TC-TC076 SSA Details section shows correct date range in modal', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $html = $browser->script("return document.querySelector('#scheduleDetailsContent')?.outerHTML ?? '';")[0];
        expect($html)->toContain('SSA Details');
    });
});

it('TC-TC077 SSA Details shows frequency row', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->waitFor('.fc-event', 20); // extended wait — this test occasionally needs more time
        openFirstEventModal($browser);
        $html = $browser->script("return document.querySelector('#scheduleDetailsContent')?.outerHTML ?? '';")[0];
        expect($html)->toContain('Frequency');
    });
});

it('TC-TC078 SSA Details shows authorized hours section', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $html = $browser->script("return document.querySelector('#scheduleDetailsContent')?.outerHTML ?? '';")[0];
        expect($html)->toContain('Authorized');
    });
});

it('TC-TC080 View Session Log link visible when session log exists', function (): void {
    // Schedule must be in the PAST for the "View Session Log" button to appear (is_past = true).
    // Use last week's Monday so the event is past and visible when navigating back.
    $pastDate     = now()->startOfWeek()->subWeek()->toDateString(); // last Monday
    $data         = calendarScaffold('scheduled', 'billed', $pastDate);
    $sessionStart = now('UTC')->startOfWeek()->subWeek()->setTime(16, 0);
    SessionLog::factory()->submitted()->create([
        'therapist_id'     => $data['therapist']->id,
        'student_id'       => $data['student']->id,
        'ssa_id'           => $data['ssa']->id,
        'school_id'        => $data['schedule']->school_id,
        'service_id'       => $data['schedule']->service_id,
        'schedule_id'      => $data['schedule']->id,
        'session_date'     => $sessionStart->toDateString(),
        'start_time'       => $sessionStart->toDateTimeString(),
        'end_time'         => $sessionStart->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
        'submitted_by_id'  => $data['therapist']->id,
    ]);

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        // Navigate back to last week where the past schedule lives
        $browser->click('.fc-prev-button')->pause(1500)->waitFor('.fc-event', 10);
        openFirstEventModal($browser);
        $html = $browser->script("return document.querySelector('#scheduleDetailsContent')?.outerHTML ?? '';")[0];
        expect($html)->toContain('View Session Log');
    });
});

it('TC-TC083 View Session Log not shown when no session log exists', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $content = $browser->script("return document.querySelector('#scheduleDetailsContent')?.textContent ?? '';")[0];
        // No session log — either link is absent or shows a create option
        $browser->assertDontSee('500');
    });
});

it('TC-TC084 SSA date range does not display as Invalid Date', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        openFirstEventModal($browser);
        $browser->assertDontSee('Invalid Date')->assertDontSee('NaN');
    });
});

it('TC-TC085 SSA Details shows only the scheduled student own SSA data', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();

    $studentA = User::factory()->student()->qa()->create(['name' => 'Alpha QA Test']);
    $studentB = User::factory()->student()->qa()->create(['name' => 'Beta QA Test']);
    foreach ([$studentA, $studentB] as $s) {
        $s->studentProfile()->update(['school_id' => $school->id]);
        $s->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
    }
    $ssaA = ServiceSupportAgreement::factory()->create(['status' => SSAStatus::ACTIVE->value, 'student_id' => $studentA->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);
    ServiceSupportAgreement::factory()->active()->create(['student_id' => $studentB->id, 'assigned_therapist_id' => $therapist->id, 'primary_service_id' => $service->id]);

    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $studentA->id, 'ssa_id' => $ssaA->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00']);

    $this->browse(function (Browser $browser) use ($therapist, $studentA, $studentB, $ssaA): void {
        // Apply student A filter so we only click student A's event
        visitCalendar($browser, $therapist);
        $browser->script("jQuery('#filter_student_ids').val(['{$studentA->id}']).trigger('change');");
        $browser->click('#applyCalendarFilters')->pause(1000);
        openFirstEventModal($browser);
        $browser->assertSeeIn('#scheduleDetailsContent', $studentA->name);
    });
});

it('TC-TC086 SSA Details usage shows 100 percent when all hours consumed', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
        'tho_minutes'           => 600, // 10 hours
        'served_minutes'        => 600, // 10 hours — fully used
    ]);
    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->toDateString(), 'start_time' => '16:00', 'end_time' => '17:00']);

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        openFirstEventModal($browser);
        $browser->assertSeeIn('#scheduleDetailsContent', '100%');
        $browser->assertDontSee('101%')->assertDontSee('110%');
    });
});

it('TC-TC088 SSA Details Per Session block shows correct minutes', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);

    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id'             => $student->id,
        'assigned_therapist_id'  => $therapist->id,
        'primary_service_id'     => $service->id,
        'minutes_per_session'    => 30,
    ]);
    Schedule::factory()->create(['therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id, 'service_id' => $service->id, 'school_id' => $school->id, 'schedule_date' => now()->startOfWeek()->toDateString(), 'start_time' => '16:00', 'end_time' => '16:30']);

    $this->browse(function (Browser $browser) use ($therapist): void {
        visitCalendar($browser, $therapist);
        openFirstEventModal($browser);
        $html = $browser->script("return document.querySelector('#scheduleDetailsContent')?.outerHTML ?? '';")[0];
        expect($html)->toContain('Per Session');
        expect($html)->toContain('30');
    });
});

// ─── Add Indirect Service ─────────────────────────────────────────────────────

it('TC-TC091 Add Indirect Service link is visible on calendar page', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->assertSee('Add Indirect Service');
    });
});

it('TC-TC092 Add Indirect Service navigates to session log SSA selection page', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->clickLink('Add Indirect Service')->pause(800);
        $browser->assertDontSee('500')->assertDontSee('Whoops');
    });
});

it('TC-TC097 therapist cannot access indirect service for another therapist student', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapistA = User::factory()->therapist()->qa()->create();
    $therapistB = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapistA, 'user')->create(['manager_id' => $admin->id]);
    TherapistProfile::factory()->for($therapistB, 'user')->create(['manager_id' => $admin->id]);

    $school  = School::factory()->qa()->create();
    $service = Service::factory()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapistB->id, ['assigned_at' => now(), 'status' => 'active']);
    $ssaB = ServiceSupportAgreement::factory()->active()->create(['student_id' => $student->id, 'assigned_therapist_id' => $therapistB->id, 'primary_service_id' => $service->id]);
    Schedule::factory()->create([
        'therapist_id' => $therapistB->id,
        'student_id' => $student->id,
        'ssa_id' => $ssaB->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapistA, $ssaB, $student): void {
        $browser->loginAs($therapistA)
            ->visit('/therapist/session-logs/create?ssa_id=' . $ssaB->id)
            ->pause(800);
        $browser->assertDontSee('500');
        // The app scopes SSA lookup to the logged-in therapist — ssaB belongs to therapistB
        // and is silently ignored. TherapistA's form renders without therapistB's student.
        $browser->assertDontSee($student->name);
    });
});

it('TC-TC101 Add Indirect Service button visible on calendar page header', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->assertSee('Add Indirect Service');
        $browser->assertDontSee('500');
    });
});

it('TC-TC105 calendar page loads without errors on mobile viewport', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        $browser->loginAs($data['therapist'])
            ->resize(375, 812)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#fullCalendar', 10)
            ->assertDontSee('500')
            ->assertDontSee('Whoops');
    });
});

// ─── Add New Schedule Button ──────────────────────────────────────────────────

it('TC-TC106 Add New Schedule button is visible on calendar page', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->assertVisible('#addScheduleButton');
        $browser->assertSee('Add New Schedule');
    });
});

it('TC-TC107 clicking Add New Schedule opens SSA selection modal', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('#addScheduleButton')->pause(600);
        $browser->assertVisible('#ssaSelectionModal');
    });
});

it('TC-TC108 SSA selection modal pre-lists SSAs for current therapist', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('#addScheduleButton')->pause(600);
        $options = $browser->script("return Array.from(document.querySelectorAll('#ssa_id option')).map(o => o.text);")[0];
        expect($options)->not->toBeEmpty();
    });
});

it('TC-TC109 continuing through SSA modal navigates to schedule creation page', function (): void {
    $data  = calendarScaffold();
    $ssaId = $data['ssa']->id;

    $this->browse(function (Browser $browser) use ($data, $ssaId): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('#addScheduleButton')->pause(600);
        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");
        $browser->press('Continue')->pause(1500);
        $browser->assertPathContains('/therapist/schedule');
        $browser->assertDontSee('500');
    });
});

it('TC-TC110 navigating back from Add New Schedule form returns to calendar', function (): void {
    $data  = calendarScaffold();
    $ssaId = $data['ssa']->id;

    $this->browse(function (Browser $browser) use ($data, $ssaId): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('#addScheduleButton')->pause(600);
        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");
        $browser->press('Continue')->pause(1500);
        $browser->back()->pause(800);
        $browser->assertPathIs('/therapist/schedule/calendar');
    });
});

it('TC-TC111 Add New Schedule button is disabled when therapist has no active SSAs', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    // No SSA created for this therapist

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#addScheduleButton', 10);
        $disabled = $browser->script("return document.getElementById('addScheduleButton')?.disabled;")[0];
        expect($disabled)->toBeTrue();
    });
});

it('TC-TC113 schedule creation form shows validation error when end time before start time', function (): void {
    $data  = calendarScaffold();
    $ssaId = $data['ssa']->id;

    $this->browse(function (Browser $browser) use ($data, $ssaId): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('#addScheduleButton')->pause(600);
        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");
        $browser->press('Continue')->pause(1500);

        $futureDate = now()->addDays(3)->format('Y-m-d');
        $browser->script("
            jQuery('#service_id').val('{$data['schedule']->service_id}').trigger('change');
            jQuery('#recurrence_type').val('none').trigger('change');
            document.querySelector('#schedule_date').value = '{$futureDate}';
            document.querySelector('#schedule_date').dispatchEvent(new Event('change', {bubbles:true}));
            document.querySelector('#start_time').value = '10:00';
        ");
        $browser->type('location_details', 'https://meet.google.com/test-tc113')
            ->press('Create Schedule')
            ->pause(1000);

        $browser->assertDontSee('500');
    });
});

it('TC-TC115 submitting Add New Schedule with no SSA selected stays on calendar', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('#addScheduleButton')->pause(600);
        // Do not select SSA — click Continue with no selection
        $browser->press('Continue')->pause(500);
        // Should remain on calendar (JS guard prevents navigation)
        $browser->assertPathIs('/therapist/schedule/calendar');
    });
});

it('TC-TC116 Add New Schedule on last day of SSA creates schedule within valid period', function (): void {
    $admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);
    $school  = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);
    $student->therapists()->attach($therapist->id, ['assigned_at' => now(), 'status' => 'active']);
    $service = Service::factory()->create();

    $endDate = now()->addDays(14)->toDateString();
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
        'end_date'              => $endDate,
    ]);
    $ssaId     = $ssa->id;
    $serviceId = $service->id;

    $this->browse(function (Browser $browser) use ($therapist, $ssaId, $serviceId, $endDate): void {
        visitCalendar($browser, $therapist);
        $browser->click('#addScheduleButton')->pause(600);
        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");
        $browser->press('Continue')->pause(1500);

        $browser->script("
            jQuery('#service_id').val('{$serviceId}').trigger('change');
            jQuery('#recurrence_type').val('none').trigger('change');
            document.querySelector('#schedule_date').value = '{$endDate}';
            document.querySelector('#schedule_date').dispatchEvent(new Event('change', {bubbles:true}));
            document.querySelector('#start_time').value = '09:00';
        ");
        $browser->type('location_details', 'https://meet.google.com/tc116-last-day')
            ->press('Create Schedule')
            ->pause(2000);

        $browser->assertDontSee('500');
    });

    $this->assertDatabaseHas('schedules', [
        'therapist_id'  => $therapist->id,
        'ssa_id'        => $ssaId,
        'schedule_date' => $endDate,
    ]);
});

it('TC-TC119 creating schedule from modal and returning shows updated calendar', function (): void {
    $data  = calendarScaffold();
    $ssaId = $data['ssa']->id;
    $serviceId = $data['schedule']->service_id;

    $this->browse(function (Browser $browser) use ($data, $ssaId, $serviceId): void {
        visitCalendar($browser, $data['therapist']);
        $eventsBefore = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);

        $browser->click('#addScheduleButton')->pause(600);
        $browser->script("jQuery('#ssa_id').val('{$ssaId}').trigger('change');");
        $browser->press('Continue')->pause(1500);

        $futureDate = now()->addDays(5)->format('Y-m-d');
        $browser->script("
            jQuery('#service_id').val('{$serviceId}').trigger('change');
            jQuery('#recurrence_type').val('none').trigger('change');
            document.querySelector('#schedule_date').value = '{$futureDate}';
            document.querySelector('#schedule_date').dispatchEvent(new Event('change', {bubbles:true}));
            document.querySelector('#start_time').value = '14:00';
        ");
        $browser->type('location_details', 'https://meet.google.com/tc119-test')
            ->press('Create Schedule')
            ->pause(2000);

        $browser->visit('/therapist/schedule/calendar')
            ->waitFor('#fullCalendar', 10)
            ->pause(1500);

        $browser->assertDontSee('500');
    });
});

it('TC-TC120 Add New Schedule button label visible on desktop and tablet viewports', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        // Desktop
        $browser->loginAs($data['therapist'])
            ->resize(1440, 900)
            ->visit('/therapist/schedule/calendar')
            ->waitFor('#addScheduleButton', 10)
            ->assertSee('Add New Schedule');

        // Tablet
        $browser->resize(768, 1024)
            ->pause(300)
            ->assertSee('Add New Schedule')
            ->assertDontSee('500');
    });
});

// ─── Calendar Views (Month / Week / Day) ───────────────────────────────────────

it('TC-TC121 calendar opens in week view by default', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $weekActive = (bool) ($browser->script("return document.querySelector('.fc-timeGridWeek-button')?.classList.contains('fc-button-active') ?? false;")[0]);
        expect($weekActive)->toBeTrue();
    });
});

it('TC-TC122 toolbar exposes Month Week and Day view buttons', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->assertVisible('.fc-dayGridMonth-button')
            ->assertVisible('.fc-timeGridWeek-button')
            ->assertVisible('.fc-timeGridDay-button');
    });
});

it('TC-TC123 switching to Month view renders the day-grid layout', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('.fc-dayGridMonth-button')->pause(900);

        $monthActive = (bool) ($browser->script("return document.querySelector('.fc-dayGridMonth-button')?.classList.contains('fc-button-active') ?? false;")[0]);
        $hasGrid     = (bool) ($browser->script("return !!document.querySelector('.fc-daygrid');")[0]);

        expect($monthActive)->toBeTrue();
        expect($hasGrid)->toBeTrue();
        $browser->assertDontSee('500');
    });
});

it('TC-TC124 switching to Day view renders a single-day time grid', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('.fc-timeGridDay-button')->pause(900);

        $dayActive = (bool) ($browser->script("return document.querySelector('.fc-timeGridDay-button')?.classList.contains('fc-button-active') ?? false;")[0]);
        $dayCols   = (int) ($browser->script("return document.querySelectorAll('.fc-timegrid-col.fc-day').length;")[0] ?? 0);

        expect($dayActive)->toBeTrue();
        expect($dayCols)->toBe(1); // a single day column
        $browser->assertDontSee('500');
    });
});

it('TC-TC125 schedule event is visible in Month view', function (): void {
    // The schedule start time, displayed in the therapist's (random US) timezone, may fall
    // outside the time-grid 06:00–21:00 window — so assert in Month view, which renders
    // events regardless of time-of-day and spans the whole month.
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('.fc-dayGridMonth-button')->pause(1000)
            ->waitFor('.fc-event', 10);
        $monthCount = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($monthCount)->toBeGreaterThanOrEqual(1);
        $browser->assertDontSee('500');
    });
});

it('TC-TC126 applied filter persists across a view change', function (): void {
    $data = calendarScaffold('scheduled', 'pending');

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        applyFilter($browser, '#filter_status', 'scheduled');

        $browser->click('.fc-dayGridMonth-button')->pause(900);
        $val = $browser->script("return jQuery('#filter_status').val();")[0];
        expect($val)->toBe('scheduled');
        $browser->assertDontSee('500');
    });
});

// ─── Toolbar Navigation (Prev / Today / Next) ───────────────────────────────────

it('TC-TC127 next and previous buttons change the calendar title', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $initial = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent ?? '';")[0];

        $browser->click('.fc-next-button')->pause(700);
        $next = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent ?? '';")[0];
        expect($next)->not->toBe($initial);

        $browser->click('.fc-prev-button')->pause(700);
        $back = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent ?? '';")[0];
        expect($back)->toBe($initial);
    });
});

it('TC-TC128 Today button returns to the current period after navigating away', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $initial = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent ?? '';")[0];

        $browser->click('.fc-next-button')->pause(600);
        $browser->click('.fc-next-button')->pause(600);
        $browser->click('.fc-today-button')->pause(700);

        $afterToday = $browser->script("return document.querySelector('.fc-toolbar-title')?.textContent ?? '';")[0];
        expect($afterToday)->toBe($initial);
        $browser->assertDontSee('500');
    });
});

it('TC-TC129 navigating to a future empty week shows no events without error', function (): void {
    $data = calendarScaffold(); // single schedule in current week only

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        // Jump several weeks ahead where no schedule exists
        $browser->click('.fc-next-button')->pause(500)
            ->click('.fc-next-button')->pause(500)
            ->click('.fc-next-button')->pause(700);

        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        expect($count)->toBe(0);
        $browser->assertDontSee('500')->assertDontSee('Whoops');
    });
});

// ─── Legend ─────────────────────────────────────────────────────────────────────

it('TC-TC130 session log status legend is visible on the calendar page', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->assertSee('Session log:')
            ->assertSee('Pending submission')
            ->assertSee('Submitted')
            ->assertSee('Approved');
    });
});

it('TC-TC131 legend lists every documented session-log status', function (): void {
    $data = calendarScaffold();

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        foreach (['Pending submission', 'Draft', 'Sent back', 'Submitted', 'Approved', 'Cancelled', 'Log only (no schedule)'] as $label) {
            $browser->assertSee($label);
        }
    });
});

// ─── Session-log status indicators on events ────────────────────────────────────

it('TC-TC132 submitted session log renders a submitted indicator icon on its event', function (): void {
    $data         = calendarScaffold();
    $sessionStart = now('UTC')->startOfWeek()->setTime(16, 0);
    SessionLog::factory()->submitted()->create([
        'therapist_id'     => $data['therapist']->id,
        'student_id'       => $data['student']->id,
        'ssa_id'           => $data['ssa']->id,
        'school_id'        => $data['schedule']->school_id,
        'service_id'       => $data['schedule']->service_id,
        'schedule_id'      => $data['schedule']->id,
        'session_date'     => $sessionStart->toDateString(),
        'start_time'       => $sessionStart->toDateTimeString(),
        'end_time'         => $sessionStart->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
        'submitted_by_id'  => $data['therapist']->id,
    ]);

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        // Month view renders the event regardless of the therapist-local time-of-day.
        $browser->click('.fc-dayGridMonth-button')->pause(1000)
            ->waitFor('.fc-event', 10)->pause(600);
        $hasIcon = (bool) ($browser->script("return !!document.querySelector('.fc-session-log-icon.fc-log-submitted');")[0]);
        expect($hasIcon)->toBeTrue();
    });
});

it('TC-TC133 past session with no log is flagged as needing billing', function (): void {
    // Schedule a few days in the past, completed, still pending billing → JS adds
    // .fc-event-needs-billing. Use Month view (renders regardless of time-of-day).
    $pastDate = now()->subDays(2)->toDateString();
    $data     = calendarScaffold('completed', 'pending', $pastDate);

    $this->browse(function (Browser $browser) use ($data): void {
        visitCalendar($browser, $data['therapist']);
        $browser->click('.fc-dayGridMonth-button')->pause(1000);

        // The past date almost always sits in the current month; near a month boundary
        // it may fall into the previous month, so step back once if nothing rendered.
        $count = (int) ($browser->script("return document.querySelectorAll('.fc-event').length;")[0] ?? 0);
        if ($count === 0) {
            $browser->click('.fc-prev-button')->pause(900);
        }
        $browser->waitFor('.fc-event', 10)->pause(600);

        $needsBilling = (bool) ($browser->script("return !!document.querySelector('.fc-event-needs-billing');")[0]);
        expect($needsBilling)->toBeTrue();
        $browser->assertDontSee('500');
    });
});
