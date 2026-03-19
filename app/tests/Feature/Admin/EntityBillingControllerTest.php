<?php

declare(strict_types=1);

use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Models\BillingSchedule;
use App\Models\School;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('show', function (): void {
    it('returns global defaults when no custom config exists for school', function (): void {
        $school = School::factory()->create();

        $response = $this->getJson(route('admin.billing.entity-config.show', [
            'entity_type' => 'school',
            'entity_id' => $school->id,
        ]));

        $response->assertOk()
            ->assertJson([
                'is_default' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'billing_mode',
                    'frequency',
                    'generation_day_type',
                    'min_grace_days',
                    'payment_terms_days',
                    'auto_generate',
                    'auto_send',
                ],
            ]);
    });

    it('returns custom config when schedule exists for school', function (): void {
        $school = School::factory()->create();
        $schedule = BillingSchedule::factory()->create([
            'schedulable_type' => 'App\\Models\\School',
            'schedulable_id' => $school->id,
            'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
            'billing_mode' => BillingMode::ADVANCE->value,
            'frequency' => BillingFrequency::MONTHLY->value,
        ]);

        $response = $this->getJson(route('admin.billing.entity-config.show', [
            'entity_type' => 'school',
            'entity_id' => $school->id,
        ]));

        $response->assertOk()
            ->assertJson([
                'is_default' => false,
                'data' => [
                    'id' => $schedule->id,
                    'billing_mode' => 'advance',
                    'frequency' => 'monthly',
                ],
            ]);
    });

    it('returns global defaults for therapist with no custom config', function (): void {
        $therapist = User::factory()->therapist()->create();

        $response = $this->getJson(route('admin.billing.entity-config.show', [
            'entity_type' => 'therapist',
            'entity_id' => $therapist->id,
        ]));

        $response->assertOk()
            ->assertJson(['is_default' => true]);
    });

    it('rejects invalid entity type', function (): void {
        $response = $this->getJson(route('admin.billing.entity-config.show', [
            'entity_type' => 'invalid',
            'entity_id' => 1,
        ]));

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invalid entity type.']);
    });
});

describe('storeOrUpdate', function (): void {
    it('creates new schedule for school', function (): void {
        $school = School::factory()->create();

        $response = $this->postJson(route('admin.billing.entity-config.store'), [
            'entity_type' => 'school',
            'entity_id' => $school->id,
            'billing_mode' => 'advance',
            'frequency' => 'monthly',
            'generation_day_type' => 'day_of_week',
            'generation_day_of_week' => 2,
            'min_grace_days' => 3,
            'payment_terms_days' => 30,
            'auto_generate' => true,
            'auto_send' => false,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('billing_schedules', [
            'schedulable_type' => 'App\\Models\\School',
            'schedulable_id' => $school->id,
            'schedule_type' => 'school_invoice',
            'billing_mode' => 'advance',
            'frequency' => 'monthly',
        ]);
    });

    it('updates existing schedule', function (): void {
        $school = School::factory()->create();
        BillingSchedule::factory()->create([
            'schedulable_type' => 'App\\Models\\School',
            'schedulable_id' => $school->id,
            'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
            'billing_mode' => BillingMode::STANDARD->value,
            'frequency' => BillingFrequency::SEMI_MONTHLY->value,
        ]);

        $response = $this->postJson(route('admin.billing.entity-config.store'), [
            'entity_type' => 'school',
            'entity_id' => $school->id,
            'billing_mode' => 'advance',
            'frequency' => 'monthly',
            'generation_day_type' => 'fixed_delay',
            'generation_delay_days' => 5,
            'min_grace_days' => 2,
            'payment_terms_days' => 30,
            'auto_generate' => true,
            'auto_send' => false,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('billing_schedules', [
            'schedulable_type' => 'App\\Models\\School',
            'schedulable_id' => $school->id,
            'billing_mode' => 'advance',
            'frequency' => 'monthly',
        ]);
    });

    it('creates schedule for therapist', function (): void {
        $therapist = User::factory()->therapist()->create();

        $response = $this->postJson(route('admin.billing.entity-config.store'), [
            'entity_type' => 'therapist',
            'entity_id' => $therapist->id,
            'billing_mode' => 'standard',
            'frequency' => 'semi_monthly',
            'generation_day_type' => 'day_of_week',
            'generation_day_of_week' => 2,
            'min_grace_days' => 2,
            'payment_terms_days' => 30,
            'auto_generate' => true,
            'auto_send' => false,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('billing_schedules', [
            'schedulable_type' => 'App\\Models\\User',
            'schedulable_id' => $therapist->id,
            'schedule_type' => 'therapist_bill',
        ]);
    });

    it('returns validation errors for missing fields', function (): void {
        $response = $this->postJson(route('admin.billing.entity-config.store'), [
            'entity_type' => 'school',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['entity_id', 'billing_mode', 'frequency', 'generation_day_type', 'min_grace_days', 'payment_terms_days']);
    });
});

describe('destroy', function (): void {
    it('deletes custom schedule and returns success', function (): void {
        $school = School::factory()->create();
        BillingSchedule::factory()->create([
            'schedulable_type' => 'App\\Models\\School',
            'schedulable_id' => $school->id,
            'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        ]);

        $response = $this->deleteJson(route('admin.billing.entity-config.destroy', [
            'entity_type' => 'school',
            'entity_id' => $school->id,
        ]));

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('billing_schedules', [
            'schedulable_type' => 'App\\Models\\School',
            'schedulable_id' => $school->id,
        ]);
    });

    it('returns 404 when no custom config exists', function (): void {
        $school = School::factory()->create();

        $response = $this->deleteJson(route('admin.billing.entity-config.destroy', [
            'entity_type' => 'school',
            'entity_id' => $school->id,
        ]));

        $response->assertStatus(404);
    });
});

describe('authorization', function (): void {
    it('denies non-admin access', function (): void {
        $therapist = User::factory()->therapist()->create();
        $this->actingAs($therapist);

        $response = $this->getJson(route('admin.billing.entity-config.show', [
            'entity_type' => 'school',
            'entity_id' => 1,
        ]));

        $response->assertForbidden();
    });
});
