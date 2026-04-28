<?php

use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\Domain\Billing\Services\TherapistBillService;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Invoice\Services\CompanyInfoService;
use App\DTOs\SendTherapistBillDTO;
use App\Enums\TherapistBillStatus;
use App\Models\SessionLog;
use App\Models\Setting;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->repository = Mockery::mock(TherapistBillRepositoryInterface::class);
    $this->companyInfoService = new CompanyInfoService;
    $this->ledgerService = Mockery::mock(LedgerService::class);
    $this->service = new TherapistBillService(
        $this->repository,
        $this->companyInfoService,
        $this->ledgerService,
    );

    // Set up company settings
    Setting::set('company.name', 'LD Expert LLP', 'string', 'company');
    Setting::set('company.address', '123 Company St', 'string', 'company');
    Setting::set('company.phone', '555-1234', 'string', 'company');
    Setting::set('company.email', 'info@ldexpert.org', 'string', 'company');
});

afterEach(function () {
    Mockery::close();
});

test('therapist bill service calculates totals correctly', function () {
    $sessionLogs = Collection::make([
        new SessionLog(['therapist_billable_amount' => 100.00]),
        new SessionLog(['therapist_billable_amount' => 150.00]),
        new SessionLog(['therapist_billable_amount' => 50.00]),
    ]);

    $totals = $this->service->calculateTotals($sessionLogs);

    expect($totals)->toMatchArray([
        'subtotal' => 300.00,
        'adjustments_total' => 0.00,
        'total_due' => 300.00,
    ]);
});

test('therapist bill service copies therapist snapshot correctly', function () {
    $therapist = User::factory()->therapist()->create([
        'name' => 'John Therapist',
        'email' => 'john@example.com',
    ]);

    $therapist->therapistProfile()->create(
        \App\Models\TherapistProfile::factory()->make([
            'user_id' => $therapist->id,
            'personal_email' => 'john.personal@example.com',
            'phone' => '555-1234',
            'address' => '123 Therapist St',
        ])->toArray()
    );

    // Refresh to load the relationship
    $therapist->refresh();
    $therapist->load('therapistProfile');

    $snapshot = $this->service->copyTherapistSnapshot($therapist);

    expect($snapshot)->toMatchArray([
        'therapist_name' => 'John Therapist',
        'therapist_email' => 'john.personal@example.com',
        'therapist_phone' => '555-1234',
        'therapist_address' => '123 Therapist St',
    ]);
});

test('therapist bill service copies company snapshot correctly', function () {
    $snapshot = $this->service->copyCompanySnapshot();

    expect($snapshot)->toHaveKeys([
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_tax_id',
    ]);
});

test('therapist bill service sends bill via email', function () {
    $user = User::factory()->admin()->create();
    $bill = TherapistBill::factory()->create([
        'status' => TherapistBillStatus::DRAFT->value,
        'therapist_email' => 'therapist@example.com',
    ]);

    $this->repository->shouldReceive('markAsSent')
        ->once()
        ->with($bill, $user->id)
        ->andReturn($bill);

    $dto = SendTherapistBillDTO::fromArray([
        'email' => null,
        'message' => null,
    ]);

    $this->ledgerService->shouldReceive('createBillGeneratedEntry')
        ->once()
        ->with($bill);

    $result = $this->service->sendBill($user, $bill, $dto);

    Mail::assertSent(\App\Mail\TherapistBillMail::class);
    expect($result)->toBe($bill);
});

test('therapist bill service prevents sending zero amount bill', function () {
    $user = User::factory()->admin()->create();
    $bill = TherapistBill::factory()->create([
        'status' => TherapistBillStatus::DRAFT->value,
        'total_due' => 0,
        'therapist_email' => 'therapist@example.com',
    ]);

    $dto = SendTherapistBillDTO::fromArray([
        'email' => null,
        'message' => null,
    ]);

    $this->repository->shouldNotReceive('markAsSent');
    $this->ledgerService->shouldNotReceive('createBillGeneratedEntry');

    $this->service->sendBill($user, $bill, $dto);
})->throws(\InvalidArgumentException::class, 'Zero amount bills cannot be sent.');
