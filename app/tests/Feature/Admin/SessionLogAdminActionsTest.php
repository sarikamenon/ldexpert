<?php

declare(strict_types=1);

use App\Models\SessionLog;
use App\Models\User;

test('admin can approve submitted session log', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->submitted()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.session-logs.approve', $sessionLog));

    $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
    $sessionLog->refresh();
    expect($sessionLog->isApproved())->toBeTrue();
    expect($sessionLog->approved_at)->not->toBeNull();
    expect($sessionLog->approved_by_id)->toBe($admin->id);
});

test('admin approve via ajax returns json instead of redirecting', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->submitted()->create();

    $response = $this->actingAs($admin)
        ->postJson(route('admin.session-logs.approve', $sessionLog));

    $response->assertOk()
        ->assertJson(['success' => true, 'message' => 'Session log approved.']);
    expect($sessionLog->fresh()->isApproved())->toBeTrue();
});

test('admin cannot approve draft session log', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->draft()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.session-logs.approve', $sessionLog));

    $response->assertForbidden();
});

test('admin can cancel draft session log', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->draft()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.session-logs.cancel', $sessionLog), [
            'cancellation_reason' => 'Cancelled by admin for review',
        ]);

    $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
    $sessionLog->refresh();
    expect($sessionLog->isCancelled())->toBeTrue();
    expect($sessionLog->cancellation_reason)->not->toBeNull();
});

test('admin can cancel submitted session log', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->submitted()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.session-logs.cancel', $sessionLog), [
            'cancellation_reason' => 'Cancelled by admin',
        ]);

    $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
    $sessionLog->refresh();
    expect($sessionLog->isCancelled())->toBeTrue();
});

test('admin cannot cancel approved session log', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->approved()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.session-logs.cancel', $sessionLog), [
            'cancellation_reason' => 'Test',
        ]);

    $response->assertForbidden();
});
