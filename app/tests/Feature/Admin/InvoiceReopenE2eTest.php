<?php

declare(strict_types=1);

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\DTOs\AttachSessionsDTO;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\Finance\Invoice\ReopenInvoiceDTO;
use App\DTOs\SendInvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');

    // Fixed $100/schedule so advance charge lines are deterministic.
    $this->mock(SessionLogRateService::class, function ($mock) {
        $mock->shouldReceive('calculateDualBilling')->andReturn([
            'school' => ['invoice_amount' => 100.0],
            'therapist' => ['billable_amount' => 50.0],
        ]);
    });

    $this->service = app(InvoiceService::class);
    $this->ledger = app(LedgerService::class);
    $this->admin = User::factory()->admin()->create();
});

function e2eAdvanceSchool(): School
{
    return School::factory()->create(['is_private_student' => true, 'state' => 'CA']);
}

function e2eScheduleInPeriod(School $school): Schedule
{
    return Schedule::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => User::factory()->therapist()->create()->id,
        'student_id' => User::factory()->student()->create()->id,
        'schedule_date' => '2026-06-10',
        'invoice_id' => null,
    ]);
}

/**
 * Build + send an advance invoice from the given schedules, leaving it SENT with
 * its invoice_generated ledger entry in place (the real start state for re-open).
 */
function buildAndSendAdvanceInvoice(User $admin, School $school, array $scheduleIds): Invoice
{
    $service = app(InvoiceService::class);

    $invoice = $service->generateInvoice($admin, CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'billing_period_start' => '2026-06-01',
        'billing_period_end' => '2026-06-30',
        'schedule_ids' => $scheduleIds,
    ]));

    return $service->sendInvoice($admin, $invoice, SendInvoiceDTO::fromArray([]));
}

test('full reopen → remove a cancelled schedule → re-send corrects the invoice end-to-end', function () {
    $school = e2eAdvanceSchool();
    $keep = e2eScheduleInPeriod($school);      // delivered/kept session
    $cancel = e2eScheduleInPeriod($school);    // the session the family cancels

    // 1. Sent advance invoice billing both schedules: 2 × $100 = $200.
    $invoice = buildAndSendAdvanceInvoice($this->admin, $school, [$keep->id, $cancel->id]);
    $schoolId = (int) $invoice->school_id;

    expect($invoice->status)->toBe(InvoiceStatus::SENT)
        ->and((float) $invoice->total)->toBe(200.0)
        ->and($invoice->lineItems()->count())->toBe(2)
        ->and($keep->fresh()->invoice_id)->toBe($invoice->id)
        ->and($cancel->fresh()->invoice_id)->toBe($invoice->id)
        ->and($this->ledger->getSchoolBalance($schoolId))->toBe(200.0);

    // 2. Re-open back to draft (reverses the ledger entry).
    $this->service->reopenInvoice($this->admin, $invoice, new ReopenInvoiceDTO('Family cancelled the 8th'));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::DRAFT)
        ->and($this->ledger->getSchoolBalance($schoolId))->toBe(0.0);

    // 3. Re-select only the kept schedule — the real edit path, not a faked total.
    $this->service->attachSessionsToDraft($invoice->fresh(), AttachSessionsDTO::fromArray([
        'schedule_ids' => [$keep->id],
    ]));

    expect($cancel->fresh()->invoice_id)->toBeNull()        // released back to the pool
        ->and($keep->fresh()->invoice_id)->toBe($invoice->id) // survives (ADR 0003 round-trip)
        ->and((float) $invoice->fresh()->total)->toBe(100.0)
        ->and($invoice->fresh()->lineItems()->count())->toBe(1);

    // 4. Re-send: fresh invoice_generated for the corrected $100 at the original date.
    $this->service->sendInvoice($this->admin, $invoice->fresh(), SendInvoiceDTO::fromArray([]));

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::SENT);

    $entry = LedgerEntry::query()
        ->where('reference_type', Invoice::class)
        ->where('reference_id', $invoice->id)
        ->where('transaction_type', TransactionType::INVOICE_GENERATED)
        ->firstOrFail();

    expect((float) $entry->amount)->toBe(100.0)
        ->and($entry->recorded_at->toDateString())->toBe('2026-06-01')
        ->and($this->ledger->getSchoolBalance($schoolId))->toBe(100.0);

    expect(Artisan::call('ledger:verify'))->toBe(0);
});

test('full reopen flow over the HTTP routes corrects and re-sends the invoice', function () {
    $school = e2eAdvanceSchool();
    $keep = e2eScheduleInPeriod($school);
    $cancel = e2eScheduleInPeriod($school);

    $invoice = buildAndSendAdvanceInvoice($this->admin, $school, [$keep->id, $cancel->id]);
    $schoolId = (int) $invoice->school_id;

    // Re-open via the route.
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.reopen', $invoice), ['reason' => 'Family cancelled the 8th'])
        ->assertRedirect(route('admin.invoices.attach-sessions', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::DRAFT);

    // Re-select only the kept schedule via the attach-sessions route.
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.attach-sessions.store', $invoice), ['schedule_ids' => [$keep->id]])
        ->assertRedirect(route('admin.invoices.show', $invoice));

    expect($cancel->fresh()->invoice_id)->toBeNull()
        ->and((float) $invoice->fresh()->total)->toBe(100.0);

    // Re-send via the send route.
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.send', $invoice), [])
        ->assertRedirect(route('admin.invoices.show', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::SENT)
        ->and($this->ledger->getSchoolBalance($schoolId))->toBe(100.0);

    expect(Artisan::call('ledger:verify'))->toBe(0);
});
