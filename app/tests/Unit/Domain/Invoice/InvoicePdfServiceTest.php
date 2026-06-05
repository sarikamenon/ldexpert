<?php

use App\Domain\Invoice\Services\InvoicePdfService;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\SessionLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('invoice pdf service generates pdf', function () {
    $service = new InvoicePdfService;

    $invoice = Invoice::factory()->create([
        'invoice_number' => 'INV-20250101-001',
        'school_display_name' => 'Test School',
        'company_name' => 'LD Expert LLP',
    ]);

    $student = User::factory()->student()->create();
    $therapist = User::factory()->therapist()->create();
    $serviceModel = Service::factory()->create();

    SessionLog::factory()->create([
        'invoice_id' => $invoice->id,
        'student_id' => $student->id,
        'therapist_id' => $therapist->id,
        'service_id' => $serviceModel->id,
        'school_invoice_amount' => 100.00,
    ]);

    $invoice->refresh();

    // Test that the method doesn't throw an error
    // The actual PDF generation is tested in feature tests
    $pdf = $service->generatePdf($invoice);

    expect($pdf)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);
});

test('resolvePaymentTermsDays returns whole-day diff between invoice and due date', function () {
    $invoice = Invoice::factory()->make([
        'invoice_date' => '2026-05-01',
        'due_date' => '2026-05-31',
    ]);

    expect(InvoicePdfService::resolvePaymentTermsDays($invoice))->toBe(30);
});

test('resolvePaymentTermsDays returns a clean integer even when dates carry time components', function () {
    // Regression: due_date->diffInDays(created_at) previously yielded a signed
    // fractional value like -29.55. Both date columns cast to date (midnight),
    // so the diff must be a non-negative whole number.
    $invoice = Invoice::factory()->make([
        'invoice_date' => '2026-06-04',
        'due_date' => '2026-07-04',
    ]);

    $days = InvoicePdfService::resolvePaymentTermsDays($invoice);

    expect($days)->toBe(30)
        ->and($days)->toBeInt();
});

test('resolvePaymentTermsDays defaults to 30 when due date is null', function () {
    $invoice = Invoice::factory()->make([
        'invoice_date' => '2026-05-01',
        'due_date' => null,
    ]);

    expect(InvoicePdfService::resolvePaymentTermsDays($invoice))->toBe(30);
});
