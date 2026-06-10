<?php

declare(strict_types=1);

use App\Domain\Billing\Services\BillingReminderService;
use App\Enums\BillingReminderType;
use App\Enums\InvoiceStatus;
use App\Models\BillingReminder;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->service = new BillingReminderService;
});

test('sends upcoming due reminder for sent invoice within reminder window', function () {
    // Settings: reminder_days_before_due = 5 (default)
    $invoice = Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(3), // within 5-day window
        'school_invoice_email' => 'billing@school.com',
    ]);

    $result = $this->service->processReminders();

    expect($result['sent'])->toBe(1)
        ->and($result['skipped'])->toBe(0);

    $this->assertDatabaseHas('billing_reminders', [
        'remindable_type' => Invoice::class,
        'remindable_id' => $invoice->id,
        'reminder_type' => BillingReminderType::UPCOMING_DUE->value,
    ]);
});

test('skips upcoming due reminder if already sent', function () {
    $invoice = Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(3),
        'school_invoice_email' => 'billing@school.com',
    ]);

    BillingReminder::create([
        'remindable_type' => Invoice::class,
        'remindable_id' => $invoice->id,
        'reminder_type' => BillingReminderType::UPCOMING_DUE->value,
        'sent_at' => now()->subDay(),
    ]);

    $result = $this->service->processReminders();

    expect($result['sent'])->toBe(0);
});

test('does not send upcoming due reminder for invoices outside window', function () {
    Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(10), // outside 5-day window
        'school_invoice_email' => 'billing@school.com',
    ]);

    $result = $this->service->processReminders();

    expect($result['sent'])->toBe(0);
});

test('does not send reminders for draft invoices', function () {
    Invoice::factory()->create([
        'status' => InvoiceStatus::DRAFT->value,
        'due_date' => now()->addDays(3),
    ]);

    $result = $this->service->processReminders();

    expect($result['sent'])->toBe(0);
});

test('sends overdue reminder for invoice past due threshold', function () {
    // Settings: reminder_days_after_due = 3 (default)
    $invoice = Invoice::factory()->sent()->create([
        'due_date' => now()->subDays(5), // 5 days overdue, > 3 threshold
        'school_invoice_email' => 'billing@school.com',
    ]);

    $result = $this->service->processReminders();

    // 1 overdue sent (no upcoming due — it's past due already)
    expect($result['sent'])->toBeGreaterThanOrEqual(1);

    $this->assertDatabaseHas('billing_reminders', [
        'remindable_type' => Invoice::class,
        'remindable_id' => $invoice->id,
        'reminder_type' => BillingReminderType::OVERDUE->value,
    ]);
});

test('skips overdue reminder if sent within repeat interval', function () {
    $invoice = Invoice::factory()->sent()->create([
        'due_date' => now()->subDays(10),
        'school_invoice_email' => 'billing@school.com',
    ]);

    // Recent overdue reminder sent 2 days ago (within 7-day repeat interval)
    BillingReminder::create([
        'remindable_type' => Invoice::class,
        'remindable_id' => $invoice->id,
        'reminder_type' => BillingReminderType::OVERDUE->value,
        'sent_at' => now()->subDays(2),
    ]);

    $result = $this->service->processReminders();

    // Should skip the overdue (may still send upcoming_due if in window, but overdue should be skipped)
    expect($result['skipped'])->toBeGreaterThanOrEqual(1);
});

test('caps overdue reminders at max_overdue_reminders setting', function () {
    $invoice = Invoice::factory()->sent()->create([
        'due_date' => now()->subDays(60),
        'school_invoice_email' => 'billing@school.com',
    ]);

    // Create 3 overdue reminders (max_overdue_reminders default is 3)
    for ($i = 0; $i < 3; $i++) {
        BillingReminder::create([
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
            'reminder_type' => BillingReminderType::OVERDUE->value,
            'sent_at' => now()->subDays(30 - ($i * 10)),
        ]);
    }

    $result = $this->service->processReminders();

    // Should be skipped because max overdue reminders reached
    expect($result['skipped'])->toBeGreaterThanOrEqual(1);
});

test('sends overdue followup when previous overdue exists and repeat interval passed', function () {
    $invoice = Invoice::factory()->sent()->create([
        'due_date' => now()->subDays(20),
        'school_invoice_email' => 'billing@school.com',
    ]);

    // Previous overdue sent 10 days ago (> 7-day repeat)
    BillingReminder::create([
        'remindable_type' => Invoice::class,
        'remindable_id' => $invoice->id,
        'reminder_type' => BillingReminderType::OVERDUE->value,
        'sent_at' => now()->subDays(10),
    ]);

    $result = $this->service->processReminders();

    expect($result['sent'])->toBeGreaterThanOrEqual(1);

    $this->assertDatabaseHas('billing_reminders', [
        'remindable_type' => Invoice::class,
        'remindable_id' => $invoice->id,
        'reminder_type' => BillingReminderType::OVERDUE_FOLLOWUP->value,
    ]);
});

test('dry run counts reminders without sending', function () {
    Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(3),
        'school_invoice_email' => 'billing@school.com',
    ]);

    $result = $this->service->processReminders(dryRun: true);

    expect($result['sent'])->toBe(1);

    // No reminder should be created in DB
    $this->assertDatabaseMissing('billing_reminders', [
        'reminder_type' => BillingReminderType::UPCOMING_DUE->value,
    ]);

    Mail::assertNothingSent();
});

test('returns zero counts when no eligible invoices', function () {
    $result = $this->service->processReminders();

    expect($result['sent'])->toBe(0)
        ->and($result['skipped'])->toBe(0);
});

test('sends reminder to the invoice email, ignoring contact email', function () {
    Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(3),
        'school_invoice_email' => 'invoice@school.com',
        'school_contact_email' => 'contact@school.com',
    ]);

    $result = $this->service->processReminders();

    expect($result['sent'])->toBe(1);
    Mail::assertSent(\App\Mail\InvoiceReminderMail::class, function ($mail) {
        return $mail->hasTo('invoice@school.com');
    });
});

test('sends no reminder email when invoice email is missing, never falling back to contact email', function () {
    Invoice::factory()->sent()->create([
        'due_date' => now()->addDays(3),
        'school_invoice_email' => null,
        'school_contact_email' => 'contact@school.com',
    ]);

    $this->service->processReminders();

    Mail::assertNothingSent();
});
