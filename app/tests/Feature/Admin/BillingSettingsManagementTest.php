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
        'default_delay_days' => 5,
        'default_payment_terms_days' => 45,
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'advance_default_frequency' => 'weekly',
        'advance_default_generation_day_type' => 'day_of_week',
        'advance_default_generation_day_of_week' => 1,
        'advance_default_delay_days' => 3,
        'advance_default_payment_terms_days' => 15,
        'advance_default_auto_generate' => true,
        'advance_default_auto_send' => true,
        'standard_default_frequency' => 'bi_weekly',
        'standard_default_generation_day_type' => 'fixed_delay',
        'standard_default_generation_day_of_week' => 4,
        'standard_default_delay_days' => 6,
        'standard_default_payment_terms_days' => 25,
        'standard_default_auto_generate' => false,
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
        'default_delay_days' => 5,
        'default_payment_terms_days' => 45,
        'max_overdue_reminders' => 5,
        'advance_default_frequency' => 'weekly',
        'advance_default_delay_days' => 3,
        'advance_default_payment_terms_days' => 15,
        'standard_default_frequency' => 'bi_weekly',
        'standard_default_delay_days' => 6,
        'standard_default_payment_terms_days' => 25,
    ]);
});

test('billing settings update validates required fields', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('admin.billing.settings.update'), []);

    $response->assertSessionHasErrors([
        'default_frequency',
        'default_generation_day_type',
        'default_generation_day_of_week',
        'default_delay_days',
        'default_payment_terms_days',
        'advance_default_frequency',
        'advance_default_generation_day_type',
        'advance_default_generation_day_of_week',
        'advance_default_delay_days',
        'advance_default_payment_terms_days',
        'standard_default_frequency',
        'standard_default_generation_day_type',
        'standard_default_generation_day_of_week',
        'standard_default_delay_days',
        'standard_default_payment_terms_days',
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
        'default_delay_days' => 31, // max is 30
        'default_payment_terms_days' => 0, // min is 1
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'advance_default_frequency' => 'invalid',
        'advance_default_generation_day_type' => 'invalid',
        'advance_default_generation_day_of_week' => 7, // max is 6
        'advance_default_delay_days' => 31, // max is 30
        'advance_default_payment_terms_days' => 91, // max is 90 (0 is allowed for advance)
        'advance_default_auto_generate' => true,
        'advance_default_auto_send' => false,
        'standard_default_frequency' => 'invalid',
        'standard_default_generation_day_type' => 'invalid',
        'standard_default_generation_day_of_week' => 7, // max is 6
        'standard_default_delay_days' => 31, // max is 30
        'standard_default_payment_terms_days' => 0, // min is 1
        'standard_default_auto_generate' => false,
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
        'default_delay_days',
        'default_payment_terms_days',
        'advance_default_frequency',
        'advance_default_generation_day_type',
        'advance_default_generation_day_of_week',
        'advance_default_delay_days',
        'advance_default_payment_terms_days',
        'standard_default_frequency',
        'standard_default_generation_day_type',
        'standard_default_generation_day_of_week',
        'standard_default_delay_days',
        'standard_default_payment_terms_days',
        'reminder_days_before_due',
        'reminder_days_after_due',
        'reminder_overdue_repeat_days',
        'max_overdue_reminders',
    ]);
});

test('billing settings update allows zero advance payment terms days', function () {
    $admin = User::factory()->admin()->create();

    $data = [
        'default_frequency' => 'monthly',
        'default_generation_day_type' => 'fixed_delay',
        'default_generation_day_of_week' => 3,
        'default_delay_days' => 5,
        'default_payment_terms_days' => 45,
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'advance_default_frequency' => 'weekly',
        'advance_default_generation_day_type' => 'day_of_week',
        'advance_default_generation_day_of_week' => 1,
        'advance_default_delay_days' => 3,
        'advance_default_payment_terms_days' => 0,
        'advance_default_auto_generate' => true,
        'advance_default_auto_send' => true,
        'standard_default_frequency' => 'bi_weekly',
        'standard_default_generation_day_type' => 'fixed_delay',
        'standard_default_generation_day_of_week' => 4,
        'standard_default_delay_days' => 6,
        'standard_default_payment_terms_days' => 25,
        'standard_default_auto_generate' => false,
        'reminder_days_before_due' => 7,
        'reminder_days_after_due' => 5,
        'reminder_overdue_repeat_days' => 10,
        'max_overdue_reminders' => 5,
    ];

    $response = $this->actingAs($admin)->put(route('admin.billing.settings.update'), $data);

    $response->assertSessionDoesntHaveErrors('advance_default_payment_terms_days');
    $this->assertDatabaseHas('billing_settings', [
        'advance_default_payment_terms_days' => 0,
    ]);
});

test('therapist cannot update billing settings', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)->put(route('admin.billing.settings.update'), [
        'default_frequency' => 'monthly',
    ]);

    $response->assertForbidden();
});
