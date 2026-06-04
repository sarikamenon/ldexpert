<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('create invoice rejects schedule_ids belonging to a different school', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
    $otherSchool = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);

    $foreignSchedule = Schedule::factory()->create([
        'school_id' => $otherSchool->id,
        'invoice_id' => null,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.invoices.store'), [
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'schedule_ids' => [$foreignSchedule->id],
    ]);

    $response->assertSessionHasErrors('schedule_ids');
});

test('create invoice rejects a non-existent schedule id', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);

    $response = $this->actingAs($admin)->post(route('admin.invoices.store'), [
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'schedule_ids' => [999999],
    ]);

    $response->assertSessionHasErrors('schedule_ids.0');
});

test('create invoice rejects schedule_ids already attached to another invoice', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);

    $existingInvoice = Invoice::factory()->create(['school_id' => $school->id]);
    $alreadyInvoiced = Schedule::factory()->create([
        'school_id' => $school->id,
        'invoice_id' => $existingInvoice->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.invoices.store'), [
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'schedule_ids' => [$alreadyInvoiced->id],
    ]);

    $response->assertSessionHasErrors('schedule_ids');
});
