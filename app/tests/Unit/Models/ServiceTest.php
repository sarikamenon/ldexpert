<?php

declare(strict_types=1);

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows schedule email for direct services regardless of send_email flag', function () {
    $service = Service::factory()->create([
        'is_direct_service' => true,
        'send_email' => false,
    ]);

    expect($service->allowsScheduleEmail())->toBeTrue();
});

it('allows schedule email for indirect service when send_email is true', function () {
    $service = Service::factory()->create([
        'is_direct_service' => false,
        'send_email' => true,
    ]);

    expect($service->allowsScheduleEmail())->toBeTrue();
});

it('blocks schedule email for indirect service when send_email is false', function () {
    $service = Service::factory()->create([
        'is_direct_service' => false,
        'send_email' => false,
    ]);

    expect($service->allowsScheduleEmail())->toBeFalse();
});
