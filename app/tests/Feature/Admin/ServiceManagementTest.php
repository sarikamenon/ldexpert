<?php

declare(strict_types=1);

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->admin()->create();
}

function servicePayload(): array
{
    return [
        'name' => 'Speech Therapy',
        'description' => 'Weekly individual session.',
        'is_direct_service' => true,
        'is_group_service' => false,
        'is_frequency_service' => true,
        'delivery_mode' => 'virtual',
        'is_billable' => true,
        'min_duration_minutes' => 30,
        'max_duration_minutes' => 60,
        'status' => ServiceStatus::ACTIVE->value,
    ];
}

it('allows admin to view services index', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    Service::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.services.index'))
        ->assertOk()
        ->assertSee('Services');
});

it('prevents non-admin access to services index', function () {
    /** @var \Tests\TestCase $this */
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('admin.services.index'))
        ->assertForbidden();
});

it('creates a service', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    $payload = servicePayload();

    $this->actingAs($admin)
        ->post(route('admin.services.store'), $payload)
        ->assertRedirect(route('admin.services.index'))
        ->assertSessionHas('status', 'Service created successfully.');

    $this->assertDatabaseHas('services', [
        'name' => 'Speech Therapy',
        'is_frequency_service' => true,
        'is_direct_service' => true,
        'is_group_service' => false,
    ]);
});

it('updates a service', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    $service = Service::factory()->create();
    $payload = servicePayload();
    $payload['name'] = 'Updated Service Name';

    $this->actingAs($admin)
        ->put(route('admin.services.update', $service), $payload)
        ->assertRedirect(route('admin.services.index'))
        ->assertSessionHas('status', 'Service updated successfully.');

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'name' => 'Updated Service Name',
    ]);
});

it('changes service status via api', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    $service = Service::factory()->create([
        'status' => ServiceStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.services.status', $service), [
            'status' => ServiceStatus::INACTIVE->value,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Service deactivated successfully.',
        ]);

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'status' => ServiceStatus::INACTIVE->value,
    ]);
});
