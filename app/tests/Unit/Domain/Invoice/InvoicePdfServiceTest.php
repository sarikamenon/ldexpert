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
