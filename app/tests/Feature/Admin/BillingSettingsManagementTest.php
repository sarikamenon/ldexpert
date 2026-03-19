<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can access billing settings edit page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.billing.settings.edit'));

    $response->assertOk();
});

test('therapist cannot access billing settings', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)->get(route('admin.billing.settings.edit'));

    $response->assertForbidden();
});

test('admin can update billing settings', function () {
    $admin = User::factory()->admin()->create();

    $data = [
        'default_frequency' => 'monthly',
        'default_generation_day_type' => 'fixed_delay',
        'default_generation_day_of_week' => 3,
        'default_min_grace_days' => 5,
        'default_payment_terms_days' => 45,
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'reminder_days_before_due' => 7,
        'reminder_days_after_due' => 5,
        'reminder_overdue_repeat_days' => 10,
        'max_overdue_reminders' => 5,
    ];

    $response = $this->actingAs($admin)->put(route('admin.billing.settings.update'), $data);

    $response->assertRedirect(route('admin.billing.settings.edit'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('billing_settings', [
        'default_frequency' => 'monthly',
        'default_payment_terms_days' => 45,
        'max_overdue_reminders' => 5,
    ]);
});

test('billing settings update validates required fields', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('admin.billing.settings.update'), []);

    $response->assertSessionHasErrors([
        'default_frequency',
        'default_generation_day_type',
        'default_generation_day_of_week',
        'default_min_grace_days',
        'default_payment_terms_days',
        'reminder_days_before_due',
        'reminder_days_after_due',
        'reminder_overdue_repeat_days',
        'max_overdue_reminders',
    ]);
});

test('billing settings update validates boundary values', function () {
    $admin = User::factory()->admin()->create();

    $data = [
        'default_frequency' => 'invalid',
        'default_generation_day_type' => 'invalid',
        'default_generation_day_of_week' => 7, // max is 6
        'default_min_grace_days' => 15, // max is 14
        'default_payment_terms_days' => 0, // min is 1
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'reminder_days_before_due' => 0, // min is 1
        'reminder_days_after_due' => 31, // max is 30
        'reminder_overdue_repeat_days' => 0, // min is 1
        'max_overdue_reminders' => 11, // max is 10
    ];

    $response = $this->actingAs($admin)->put(route('admin.billing.settings.update'), $data);

    $response->assertSessionHasErrors([
        'default_frequency',
        'default_generation_day_type',
        'default_generation_day_of_week',
        'default_min_grace_days',
        'default_payment_terms_days',
        'reminder_days_before_due',
        'reminder_days_after_due',
        'reminder_overdue_repeat_days',
        'max_overdue_reminders',
    ]);
});

test('therapist cannot update billing settings', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)->put(route('admin.billing.settings.update'), [
        'default_frequency' => 'monthly',
    ]);

    $response->assertForbidden();
});
