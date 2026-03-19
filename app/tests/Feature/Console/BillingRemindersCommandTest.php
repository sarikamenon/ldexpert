<?php

declare(strict_types=1);

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

test('billing send reminders command processes reminders', function () {
    Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(3),
        'parent_email' => 'parent@example.com',
    ]);

    $this->artisan('billing:send-reminders')
        ->assertExitCode(0)
        ->expectsOutputToContain('Sent: 1');
});

test('billing send reminders dry run does not send emails', function () {
    Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(3),
        'parent_email' => 'parent@example.com',
    ]);

    $this->artisan('billing:send-reminders', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN');

    Mail::assertNothingSent();
});

test('billing send reminders shows zero counts when no eligible invoices', function () {
    $this->artisan('billing:send-reminders')
        ->assertExitCode(0)
        ->expectsOutputToContain('Sent: 0, Skipped: 0');
});
