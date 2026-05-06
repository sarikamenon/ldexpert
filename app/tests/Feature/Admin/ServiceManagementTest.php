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
        'include_in_tho' => true,
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
        'include_in_tho' => true,
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
        'include_in_tho' => true,
    ]);
});

it('creates a service with color and send_email', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    $payload = servicePayload();
    $payload['color'] = '#FF5733';
    $payload['send_email'] = true;

    $this->actingAs($admin)
        ->post(route('admin.services.store'), $payload)
        ->assertRedirect(route('admin.services.index'));

    $this->assertDatabaseHas('services', [
        'name' => 'Speech Therapy',
        'color' => '#FF5733',
        'send_email' => true,
    ]);
});

it('rejects an invalid color format', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    $payload = servicePayload();
    $payload['color'] = 'not-a-color';

    $this->actingAs($admin)
        ->post(route('admin.services.store'), $payload)
        ->assertSessionHasErrors('color');
});

it('stores null color when color is omitted', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();

    $this->actingAs($admin)
        ->post(route('admin.services.store'), servicePayload())
        ->assertRedirect(route('admin.services.index'));

    $this->assertDatabaseHas('services', [
        'name' => 'Speech Therapy',
        'color' => null,
    ]);
});

it('defaults send_email to true for indirect service when field absent', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    $payload = servicePayload();
    $payload['is_direct_service'] = false;
    unset($payload['send_email']);

    $this->actingAs($admin)
        ->post(route('admin.services.store'), $payload)
        ->assertRedirect(route('admin.services.index'));

    $this->assertDatabaseHas('services', [
        'name' => 'Speech Therapy',
        'is_direct_service' => false,
        'send_email' => true,
    ]);
});

it('persists send_email false for indirect service', function () {
    /** @var \Tests\TestCase $this */
    $admin = admin();
    $payload = servicePayload();
    $payload['is_direct_service'] = false;
    $payload['send_email'] = false;

    $this->actingAs($admin)
        ->post(route('admin.services.store'), $payload)
        ->assertRedirect(route('admin.services.index'));

    $this->assertDatabaseHas('services', [
        'name' => 'Speech Therapy',
        'send_email' => false,
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
