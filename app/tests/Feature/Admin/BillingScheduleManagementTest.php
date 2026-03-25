<?php

declare(strict_types=1);

use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scheduleAdminUser(): User
{
    return User::factory()->admin()->create();
}

function scheduleTherapistUser(): User
{
    return User::factory()->therapist()->create();
}

// --- Authorization ---

test('admin can access billing schedules index', function () {
    $admin = scheduleAdminUser();

    $response = $this->actingAs($admin)->get(route('admin.billing.schedules.index'));

    $response->assertOk();
});

test('therapist cannot access billing schedules index', function () {
    $therapist = scheduleTherapistUser();

    $response = $this->actingAs($therapist)->get(route('admin.billing.schedules.index'));

    $response->assertForbidden();
});

test('admin can access billing schedules create form', function () {
    $admin = scheduleAdminUser();

    $response = $this->actingAs($admin)->get(route('admin.billing.schedules.create'));

    $response->assertOk();
});

// --- Store ---

test('admin can create a billing schedule', function () {
    $admin = scheduleAdminUser();
    $school = School::factory()->create();

    $data = [
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => $school->id,
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'standard',
        'frequency' => 'semi_monthly',
        'generation_day_type' => 'day_of_week',
        'generation_day_of_week' => 2,
        'min_grace_days' => 2,
        'payment_terms_days' => 30,
        'auto_generate' => true,
        'auto_send' => false,
    ];

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.store'), $data);

    $response->assertRedirect(route('admin.billing.schedules.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('billing_schedules', [
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => $school->id,
        'schedule_type' => 'school_invoice',
    ]);
});

test('store rejects duplicate schedule for same entity and type', function () {
    $admin = scheduleAdminUser();
    $school = School::factory()->create();

    BillingSchedule::factory()->forSchool($school)->create();

    $data = [
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => $school->id,
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'standard',
        'frequency' => 'monthly',
        'generation_day_type' => 'day_of_week',
        'generation_day_of_week' => 3,
        'min_grace_days' => 2,
        'payment_terms_days' => 30,
    ];

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.store'), $data);

    $response->assertSessionHasErrors('schedulable_id');
});

test('store validates required fields', function () {
    $admin = scheduleAdminUser();

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.store'), []);

    $response->assertSessionHasErrors([
        'schedulable_type',
        'schedulable_id',
        'schedule_type',
        'billing_mode',
        'frequency',
        'generation_day_type',
        'payment_terms_days',
    ]);
});

test('store requires generation_day_of_week when type is day_of_week', function () {
    $admin = scheduleAdminUser();
    $school = School::factory()->create();

    $data = [
        'schedulable_type' => 'App\\Models\\School',
        'schedulable_id' => $school->id,
        'schedule_type' => 'school_invoice',
        'billing_mode' => 'standard',
        'frequency' => 'semi_monthly',
        'generation_day_type' => 'day_of_week',
        'generation_day_of_week' => null,
        'min_grace_days' => 2,
        'payment_terms_days' => 30,
    ];

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.store'), $data);

    $response->assertSessionHasErrors('generation_day_of_week');
});

// --- Edit & Update ---

test('admin can access billing schedule edit form', function () {
    $admin = scheduleAdminUser();
    $schedule = BillingSchedule::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.billing.schedules.edit', $schedule));

    $response->assertOk();
});

test('admin can update a billing schedule', function () {
    $admin = scheduleAdminUser();
    $schedule = BillingSchedule::factory()->create();

    $data = [
        'schedulable_type' => $schedule->schedulable_type,
        'schedulable_id' => $schedule->schedulable_id,
        'schedule_type' => $schedule->schedule_type->value,
        'billing_mode' => 'advance',
        'frequency' => 'monthly',
        'generation_day_type' => 'fixed_delay',
        'generation_delay_days' => 5,
        'min_grace_days' => 3,
        'payment_terms_days' => 45,
        'auto_generate' => true,
        'auto_send' => true,
    ];

    $response = $this->actingAs($admin)->put(route('admin.billing.schedules.update', $schedule), $data);

    $response->assertRedirect(route('admin.billing.schedules.index'));
    $response->assertSessionHas('success');
});

// --- Toggle ---

test('admin can toggle billing schedule active status', function () {
    $admin = scheduleAdminUser();
    $schedule = BillingSchedule::factory()->create(['is_active' => true]);

    $response = $this->actingAs($admin)->patch(route('admin.billing.schedules.toggle', $schedule));

    $response->assertOk();
    $response->assertJson(['success' => true, 'is_active' => false]);
});

// --- Run Now ---

test('admin can run active schedule manually', function () {
    $admin = scheduleAdminUser();
    $school = School::factory()->create();
    $schedule = BillingSchedule::factory()->forSchool($school)->create(['is_active' => true]);

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.run', $schedule));

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

test('admin cannot run inactive schedule', function () {
    $admin = scheduleAdminUser();
    $schedule = BillingSchedule::factory()->inactive()->create();

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.run', $schedule));

    $response->assertForbidden();
});

// --- Run History ---

test('admin can view run history', function () {
    $admin = scheduleAdminUser();
    $schedule = BillingSchedule::factory()->create();
    BillingScheduleRun::factory()->count(3)->create(['billing_schedule_id' => $schedule->id]);

    $response = $this->actingAs($admin)->get(route('admin.billing.schedules.history', $schedule));

    $response->assertOk();
});

test('admin can load run history datatable data', function () {
    $admin = scheduleAdminUser();
    $schedule = BillingSchedule::factory()->forSchool()->create();
    BillingScheduleRun::factory()->count(2)->create(['billing_schedule_id' => $schedule->id]);

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.history.data', $schedule), [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'order' => [['column' => 0, 'dir' => 'desc']],
        'columns' => [],
        'search' => ['value' => ''],
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    expect($response->json('recordsTotal'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(2);
});

test('run history datatable returns empty data when no runs', function () {
    $admin = scheduleAdminUser();
    $schedule = BillingSchedule::factory()->forSchool()->create();

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.history.data', $schedule), [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'order' => [['column' => 0, 'dir' => 'desc']],
        'columns' => [],
        'search' => ['value' => ''],
    ]);

    $response->assertOk();
    expect($response->json('recordsTotal'))->toBe(0)
        ->and($response->json('data'))->toBe([]);
});

// --- DataTables ---

test('admin can access billing schedules data endpoint', function () {
    $admin = scheduleAdminUser();
    BillingSchedule::factory()->count(3)->create();

    $response = $this->actingAs($admin)->post(route('admin.billing.schedules.data'), [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'order' => [['column' => 0, 'dir' => 'asc']],
        'columns' => [],
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
});
