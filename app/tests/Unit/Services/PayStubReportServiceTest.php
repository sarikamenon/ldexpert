<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Finance\Services\PayStubReportService;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TherapistBillStatus;
use App\Models\TherapistBill;
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

    /**
     * @return array{therapist: User, bill: TherapistBill}
     */
    private function createTherapistWithBill(string $name, float $hourlyRate, string $billNumber, string $periodStart, string $periodEnd, float $totalDue): array
    {
        $therapist = User::factory()->therapist()->create(['name' => $name]);
        TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => $hourlyRate]);

        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'bill_number' => $billNumber,
            'therapist_name' => $name,
            'status' => TherapistBillStatus::SENT,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'total_due' => $totalDue,
            'sent_at' => now(),
            'sent_by_id' => $admin->id,
        ]);

        return ['therapist' => $therapist, 'bill' => $bill];
    }

    private function createPayment(User $therapist, TherapistBill $bill, string $paidAt, float $amount, PaymentMethod $method): TherapistBillPayment
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $payment = new TherapistBillPayment;
        $payment->therapist_id = $therapist->id;
        $payment->therapist_bill_id = $bill->id;
        $payment->paid_at = $paidAt;
        $payment->amount = $amount;
        $payment->method = $method;
        $payment->recorded_by_id = $admin->id;
        $payment->save();

        return $payment;
    }

    public function test_get_therapists_with_payments_returns_correct_aggregate(): void
    {
        $data = $this->createTherapistWithBill('Test Therapist', 60.00, 'PST-AGG-001', '2026-01-01', '2026-01-15', 600.00);

        $this->createPayment($data['therapist'], $data['bill'], '2026-01-20', 300.00, PaymentMethod::ACH);
        $this->createPayment($data['therapist'], $data['bill'], '2026-01-25', 300.00, PaymentMethod::ACH);

        $result = $this->service->getTherapistsWithPayments(2026);

        $match = array_filter($result, fn ($r) => $r['therapist_id'] === $data['therapist']->id);
        $this->assertCount(1, $match);
        $row = array_values($match)[0];
        $this->assertEquals('Test Therapist', $row['therapist_name']);
        $this->assertEquals(2, $row['payment_count']);
        $this->assertEquals(600.00, $row['total_amount']);
    }

    public function test_get_therapists_with_payments_filters_by_year(): void
    {
        $data = $this->createTherapistWithBill('Year Filter Therapist', 50.00, 'PST-YRF-001', '2026-06-01', '2026-06-15', 400.00);

        $this->createPayment($data['therapist'], $data['bill'], '2026-06-20', 400.00, PaymentMethod::ACH);

        // Should not appear in year 2025
        $result = $this->service->getTherapistsWithPayments(2025);
        $match = array_filter($result, fn ($r) => $r['therapist_id'] === $data['therapist']->id);
        $this->assertEmpty($match);
    }

    public function test_get_therapist_pay_stub_data_returns_rows_with_ytd(): void
    {
        $data = $this->createTherapistWithBill('Jane Smith', 50.00, 'PST-YTD-001', '2026-01-01', '2026-01-15', 500.00);

        $this->createPayment($data['therapist'], $data['bill'], '2026-01-20', 500.00, PaymentMethod::DIRECT_DEPOSIT);

        $result = $this->service->getTherapistPayStubData($data['therapist']->id, 2026);

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
}
