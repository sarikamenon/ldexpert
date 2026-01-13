<?php

use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Invoice\Services\CompanyInfoService;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\SendInvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->repository = Mockery::mock(InvoiceRepositoryInterface::class);
    $this->companyInfoService = new CompanyInfoService;
    $this->schoolRepository = Mockery::mock(SchoolRepositoryInterface::class);
    $this->service = new InvoiceService($this->repository, $this->companyInfoService, $this->schoolRepository);

    // Set up company settings
    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');
});

afterEach(function () {
    Mockery::close();
});

test('invoice service calculates totals correctly', function () {
    $sessionLogs = Collection::make([
        new SessionLog(['school_invoice_amount' => 100.00]),
        new SessionLog(['school_invoice_amount' => 150.00]),
        new SessionLog(['school_invoice_amount' => 50.00]),
    ]);

    $totals = $this->service->calculateTotals($sessionLogs);

    expect($totals)->toMatchArray([
        'subtotal' => 300.00,
        'tax_total' => 0.00,
        'total' => 300.00,
    ]);
});

test('invoice service copies school snapshot correctly', function () {
    $school = School::factory()->make([
        'full_name' => 'Test School Full',
        'display_name' => 'Test School',
        'address' => '123 School St',
        'state' => 'CA',
        'contact_first_name' => 'John',
        'contact_last_name' => 'Doe',
        'contact_phone' => '555-1234',
        'contact_email' => 'contact@school.com',
        'invoice_email' => 'billing@school.com',
    ]);

    $snapshot = $this->service->copySchoolSnapshot($school);

    expect($snapshot)->toMatchArray([
        'school_name' => 'Test School Full',
        'school_display_name' => 'Test School',
        'school_address' => '123 School St',
        'school_state' => 'CA',
        'school_contact_first_name' => 'John',
        'school_contact_last_name' => 'Doe',
        'school_contact_phone' => '555-1234',
        'school_contact_email' => 'contact@school.com',
        'school_invoice_email' => 'billing@school.com',
    ]);
});

test('invoice service copies company snapshot correctly', function () {
    Setting::set('company.tax_id', 'TAX123', 'string', 'company');

    $snapshot = $this->service->copyCompanySnapshot();

    expect($snapshot)->toMatchArray([
        'company_name' => 'LD Expert LLP',
        'company_address' => '123 Company St',
        'company_phone' => '555-1234',
        'company_email' => 'info@ldexpert.org',
        'company_tax_id' => 'TAX123',
    ]);
});

test('invoice service generates invoice with snapshots', function () {
    $user = User::factory()->admin()->create();
    $school = School::factory()->create([
        'full_name' => 'Test School Full',
        'display_name' => 'Test School',
    ]);

    $sessionLog1 = SessionLog::factory()->make(['id' => 1, 'school_id' => $school->id, 'school_invoice_amount' => 100.00]);
    $sessionLog2 = SessionLog::factory()->make(['id' => 2, 'school_id' => $school->id, 'school_invoice_amount' => 150.00]);
    $sessionLogs = Collection::make([$sessionLog1, $sessionLog2]);

    $dto = CreateInvoiceDTO::fromArray([
        'school_id' => $school->id,
        'invoice_date' => now()->format('Y-m-d'),
        'billing_period_start' => now()->startOfMonth()->format('Y-m-d'),
        'billing_period_end' => now()->endOfMonth()->format('Y-m-d'),
        'session_log_ids' => [1, 2],
    ]);

    $this->repository->shouldReceive('getApprovedSessionLogsForInvoice')
        ->once()
        ->with([1, 2])
        ->andReturn($sessionLogs);

    $this->schoolRepository->shouldReceive('find')
        ->once()
        ->with($school->id)
        ->andReturn($school);

    $this->repository->shouldReceive('generateInvoiceNumber')
        ->once()
        ->andReturn('INV-20250101-001');

    $invoice = Invoice::factory()->make([
        'school_id' => $school->id,
        'invoice_number' => 'INV-20250101-001',
        'status' => InvoiceStatus::DRAFT,
    ]);

    $this->repository->shouldReceive('create')
        ->once()
        ->andReturn($invoice);

    $this->repository->shouldReceive('linkSessionLogs')
        ->once()
        ->with($invoice, [1, 2]);

    $result = $this->service->generateInvoice($user, $dto);

    expect($result)->toBe($invoice);
});

test('invoice service sends invoice via email', function () {
    $user = User::factory()->admin()->create();
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::DRAFT,
        'school_invoice_email' => 'billing@school.com',
    ]);

    $dto = SendInvoiceDTO::fromArray([
        'email' => null,
        'message' => 'Test message',
    ]);

    $this->repository->shouldReceive('markAsSent')
        ->once()
        ->with($invoice, $user->id)
        ->andReturn($invoice);

    $result = $this->service->sendInvoice($user, $invoice, $dto);

    Mail::assertSent(\App\Mail\InvoiceMail::class);
    expect($result)->toBe($invoice);
});
