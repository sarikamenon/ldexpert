<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Finance\Services\PayStubReportService;
use App\Enums\PaymentMethod;
use App\Models\TherapistBillPayment;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PayStubReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayStubReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayStubReportService::class);
    }

    public function test_get_therapists_with_payments_returns_correct_aggregate(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Test Therapist']);
        TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 60.00]);

        TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2026-01-20', 'amount' => 300.00, 'method' => PaymentMethod::ACH]);
        TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2026-01-25', 'amount' => 300.00, 'method' => PaymentMethod::ACH]);

        $result = $this->service->getTherapistsWithPayments(2026);

        $match = array_filter($result, fn ($r) => $r['therapist_id'] === $therapist->id);
        $this->assertCount(1, $match);
        $row = array_values($match)[0];
        $this->assertEquals('Test Therapist', $row['therapist_name']);
        $this->assertEquals(2, $row['payment_count']);
        $this->assertEquals(600.00, $row['total_amount']);
    }

    public function test_get_therapists_with_payments_filters_by_year(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Year Filter Therapist']);
        TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 50.00]);

        TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2026-06-20', 'amount' => 400.00, 'method' => PaymentMethod::ACH]);

        $result = $this->service->getTherapistsWithPayments(2025);
        $match = array_filter($result, fn ($r) => $r['therapist_id'] === $therapist->id);
        $this->assertEmpty($match);
    }

    public function test_get_therapist_pay_stub_data_returns_rows_with_ytd(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Jane Smith']);
        TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 50.00]);

        TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2026-01-20', 'amount' => 500.00, 'method' => PaymentMethod::DIRECT_DEPOSIT]);

        $result = $this->service->getTherapistPayStubData($therapist->id, 2026);

        $this->assertCount(1, $result['rows']);
        $this->assertEquals('Jane Smith', $result['therapist_name']);
        $this->assertEquals(2026, $result['year']);
        $this->assertEquals(500.00, $result['rows'][0]['regular_pay']);
        $this->assertEquals(500.00, $result['rows'][0]['ytd_regular_pay']);
    }

    public function test_get_therapist_pay_stub_data_returns_empty_for_therapist_without_payments(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'No Payments']);

        $result = $this->service->getTherapistPayStubData($therapist->id, 2026);

        $this->assertEmpty($result['rows']);
        $this->assertEquals(0, $result['summary']['row_count']);
    }

    public function test_get_years_with_payments_returns_years_descending(): void
    {
        $therapist = User::factory()->therapist()->create();
        TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 60.00]);

        TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2025-03-10', 'amount' => 300.00]);
        TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2026-04-15', 'amount' => 300.00]);

        $years = $this->service->getYearsWithPayments($therapist->id);

        $this->assertEquals([2026, 2025], $years);
    }

    public function test_get_years_with_payments_returns_empty_for_therapist_without_payments(): void
    {
        $therapist = User::factory()->therapist()->create();

        $years = $this->service->getYearsWithPayments($therapist->id);

        $this->assertEmpty($years);
    }

    public function test_get_years_with_payments_only_returns_own_years(): void
    {
        $therapist1 = User::factory()->therapist()->create();
        $therapist2 = User::factory()->therapist()->create();
        TherapistProfile::factory()->create(['user_id' => $therapist1->id, 'hourly_rate' => 60.00]);

        TherapistBillPayment::factory()->create(['therapist_id' => $therapist1->id, 'paid_at' => '2026-03-10', 'amount' => 300.00]);
        TherapistBillPayment::factory()->create(['therapist_id' => $therapist2->id, 'paid_at' => '2025-03-10', 'amount' => 300.00]);

        $years = $this->service->getYearsWithPayments($therapist1->id);

        $this->assertEquals([2026], $years);
    }
}
