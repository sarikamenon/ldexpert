<?php

declare(strict_types=1);

use App\Domain\Finance\Services\LedgerService;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->admin()->create();
});

function reopenableInvoice(User $admin): Invoice
{
    $school = School::factory()->create(['is_private_student' => true, 'state' => 'CA']);

    $invoice = Invoice::factory()->advance()->sent($admin)->create([
        'school_id' => $school->id,
        'invoice_date' => '2026-06-01',
        'total' => 300.0,
        'subtotal' => 300.0,
    ]);

    app(LedgerService::class)->createInvoiceGeneratedEntry($invoice);

    return $invoice;
}

test('admin can re-open a sent advance invoice and is redirected to attach sessions', function () {
    $invoice = reopenableInvoice($this->admin);

    $response = $this->actingAs($this->admin)->post(
        route('admin.invoices.reopen', $invoice),
        ['reason' => 'Family cancelled a session']
    );

    $response->assertRedirect(route('admin.invoices.attach-sessions', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::DRAFT);
});

test('re-open requires a reason', function () {
    $invoice = reopenableInvoice($this->admin);

    $response = $this->actingAs($this->admin)->post(
        route('admin.invoices.reopen', $invoice),
        ['reason' => '']
    );

    $response->assertSessionHasErrors('reason');
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::SENT);
});

test('re-open is forbidden for a standard invoice', function () {
    $school = School::factory()->create();
    $standard = Invoice::factory()->sent($this->admin)->create(['school_id' => $school->id]);

    $response = $this->actingAs($this->admin)->post(
        route('admin.invoices.reopen', $standard),
        ['reason' => 'should fail']
    );

    $response->assertForbidden();
});

test('non-admin cannot re-open an invoice', function () {
    $invoice = reopenableInvoice($this->admin);
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)->post(
        route('admin.invoices.reopen', $invoice),
        ['reason' => 'should fail']
    );

    $response->assertForbidden();
});
