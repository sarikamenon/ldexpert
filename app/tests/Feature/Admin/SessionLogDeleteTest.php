<?php

declare(strict_types=1);

use App\Enums\BillingStatus;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\User;

test('admin can delete a non-approved session log and free its schedule', function () {
    $admin = User::factory()->admin()->create();
    $schedule = Schedule::factory()->create(['billing_status' => BillingStatus::BILLED]);
    $sessionLog = SessionLog::factory()->submitted()->withSchedule($schedule)->create();

    $response = $this->actingAs($admin)
        ->delete(route('admin.session-logs.destroy', $sessionLog));

    $response->assertRedirect(route('admin.session-logs.index'));
    $this->assertSoftDeleted($sessionLog);
    expect($schedule->fresh()->billing_status)->toBe(BillingStatus::PENDING);
});

test('admin cannot delete an approved session log', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->approved()->create();

    $response = $this->actingAs($admin)
        ->delete(route('admin.session-logs.destroy', $sessionLog));

    $response->assertForbidden();
    $this->assertNotSoftDeleted($sessionLog);
});
