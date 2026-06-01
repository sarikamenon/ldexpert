<?php

declare(strict_types=1);

use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->position = Position::factory()->create(['name' => 'SLP']);
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->admin()->create();
});

function internationalTherapistPayload(int $positionId, int $managerId, string $state, string $timezone): array
{
    return [
        'employee_type' => 'W2',
        'title' => 'Dr.',
        'first_name' => 'Ayesha',
        'last_name' => 'Khan',
        'personal_email' => 'ayesha.khan@example.com',
        'phone' => '555-123-4567',
        'ld_email' => 'ayesha.khan@ldexpert.com',
        'llc_name' => 'Ayesha Khan LLC',
        'address' => '1 Clifton Rd',
        'comments' => 'International therapist',
        'position_id' => $positionId,
        'state' => $state,
        'timezone' => $timezone,
        'manager_id' => $managerId,
        'max_weekly_hours' => 40,
        'hourly_rate' => 55.00,
        'dob' => '1990-01-01',
        'default_meeting_location' => 'https://meet.google.com/new',
    ];
}

it('accepts a non-US timezone and 3-letter state code', function () {
    Mail::fake();

    $payload = internationalTherapistPayload($this->position->id, $this->manager->id, 'KHI', 'Asia/Karachi');

    $response = actingAs($this->admin)->post(route('admin.therapists.store'), $payload);

    $response->assertRedirect(route('admin.therapists.index'));
    $response->assertSessionHas('status');

    $this->assertDatabaseHas('therapist_profiles', [
        'personal_email' => 'ayesha.khan@example.com',
        'state' => 'KHI',
        'timezone' => 'Asia/Karachi',
    ]);
});

it('rejects an unknown state code', function () {
    $payload = internationalTherapistPayload($this->position->id, $this->manager->id, 'ZZZ', 'Asia/Karachi');

    $response = actingAs($this->admin)->post(route('admin.therapists.store'), $payload);

    $response->assertSessionHasErrors('state');
});

it('rejects an unknown timezone', function () {
    $payload = internationalTherapistPayload($this->position->id, $this->manager->id, 'IST', 'Mars/Olympus');

    $response = actingAs($this->admin)->post(route('admin.therapists.store'), $payload);

    $response->assertSessionHasErrors('timezone');
});
