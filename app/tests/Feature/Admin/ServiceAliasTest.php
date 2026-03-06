<?php

declare(strict_types=1);

use App\Enums\SSAImportType;
use App\Models\Service;
use App\Models\ServiceAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function aliasAdmin(): User
{
    return User::factory()->admin()->create();
}

function aliasPayload(?Service $service = null): array
{
    $service ??= Service::factory()->create();

    return [
        'source' => SSAImportType::RSM->value,
        'external_name' => 'Speech Therapy Online',
        'service_id' => $service->id,
    ];
}

// ── Index ───────────────────────────────────────────────────

it('allows admin to view service aliases index', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();

    $this->actingAs($admin)
        ->get(route('admin.service-aliases.index'))
        ->assertOk()
        ->assertSee('Service Aliases');
});

it('prevents non-admin access to service aliases index', function () {
    /** @var \Tests\TestCase $this */
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('admin.service-aliases.index'))
        ->assertForbidden();
});

// ── DataTables ──────────────────────────────────────────────

it('returns data for service aliases datatable', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();
    ServiceAlias::create([
        'source' => SSAImportType::RSM->value,
        'external_name' => 'OT Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.service-aliases.data'), ['draw' => 1])
        ->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
});

it('filters datatable by source', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();
    ServiceAlias::create(['source' => 'RSM', 'external_name' => 'RSM Service', 'service_id' => $service->id]);
    ServiceAlias::create(['source' => 'MARVIN', 'external_name' => 'MARVIN Service', 'service_id' => $service->id]);

    $response = $this->actingAs($admin)
        ->post(route('admin.service-aliases.data'), ['draw' => 1, 'filter_source' => 'RSM'])
        ->assertOk();

    expect($response->json('recordsFiltered'))->toBe(1);
});

// ── Create ──────────────────────────────────────────────────

it('allows admin to view create form', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();

    $this->actingAs($admin)
        ->get(route('admin.service-aliases.create'))
        ->assertOk()
        ->assertSee('Create Service Alias');
});

it('creates a service alias', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $payload = aliasPayload();

    $this->actingAs($admin)
        ->post(route('admin.service-aliases.store'), $payload)
        ->assertRedirect(route('admin.service-aliases.index'))
        ->assertSessionHas('status', 'Service alias created successfully.');

    $this->assertDatabaseHas('service_aliases', [
        'source' => 'RSM',
        'external_name' => 'Speech Therapy Online',
        'service_id' => $payload['service_id'],
    ]);
});

it('rejects duplicate external name per source', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();

    ServiceAlias::create([
        'source' => 'RSM',
        'external_name' => 'Speech Therapy Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.service-aliases.store'), [
            'source' => 'RSM',
            'external_name' => 'Speech Therapy Online',
            'service_id' => $service->id,
        ])
        ->assertSessionHasErrors('external_name');
});

it('allows same external name for different sources', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();

    ServiceAlias::create([
        'source' => 'RSM',
        'external_name' => 'Speech Therapy Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.service-aliases.store'), [
            'source' => 'MARVIN',
            'external_name' => 'Speech Therapy Online',
            'service_id' => $service->id,
        ])
        ->assertRedirect(route('admin.service-aliases.index'));

    expect(ServiceAlias::count())->toBe(2);
});

it('validates required fields on store', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();

    $this->actingAs($admin)
        ->post(route('admin.service-aliases.store'), [])
        ->assertSessionHasErrors(['source', 'external_name', 'service_id']);
});

// ── Edit / Update ───────────────────────────────────────────

it('allows admin to view edit form', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();
    $alias = ServiceAlias::create([
        'source' => 'RSM',
        'external_name' => 'OT Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.service-aliases.edit', $alias))
        ->assertOk()
        ->assertSee('Edit Service Alias');
});

it('updates a service alias', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();
    $alias = ServiceAlias::create([
        'source' => 'RSM',
        'external_name' => 'OT Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.service-aliases.update', $alias), [
            'source' => 'RSM',
            'external_name' => 'Occupational Therapy Online',
            'service_id' => $service->id,
        ])
        ->assertRedirect(route('admin.service-aliases.index'))
        ->assertSessionHas('status', 'Service alias updated successfully.');

    $alias->refresh();
    expect($alias->external_name)->toBe('Occupational Therapy Online');
});

it('allows keeping the same external name on update', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();
    $newService = Service::factory()->create();
    $alias = ServiceAlias::create([
        'source' => 'RSM',
        'external_name' => 'OT Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.service-aliases.update', $alias), [
            'source' => 'RSM',
            'external_name' => 'OT Online',
            'service_id' => $newService->id,
        ])
        ->assertRedirect(route('admin.service-aliases.index'));

    $alias->refresh();
    expect($alias->service_id)->toBe($newService->id);
});

// ── Delete ──────────────────────────────────────────────────

it('soft-deletes a service alias', function () {
    /** @var \Tests\TestCase $this */
    $admin = aliasAdmin();
    $service = Service::factory()->create();
    $alias = ServiceAlias::create([
        'source' => 'RSM',
        'external_name' => 'OT Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.service-aliases.destroy', $alias))
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertSoftDeleted('service_aliases', ['id' => $alias->id]);
});

it('prevents non-admin from deleting aliases', function () {
    /** @var \Tests\TestCase $this */
    $therapist = User::factory()->therapist()->create();
    $service = Service::factory()->create();
    $alias = ServiceAlias::create([
        'source' => 'RSM',
        'external_name' => 'OT Online',
        'service_id' => $service->id,
    ]);

    $this->actingAs($therapist)
        ->delete(route('admin.service-aliases.destroy', $alias))
        ->assertForbidden();
});
