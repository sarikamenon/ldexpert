<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayStubReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{therapist: User, bill: TherapistBill}
     */
    private function createTherapistWithBill(string $name, float $hourlyRate, string $periodStart, string $periodEnd): array
    {
        $therapist = User::factory()->therapist()->create(['name' => $name]);
        TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => $hourlyRate]);

        $bill = TherapistBill::factory()->sent()->create([
            'therapist_id' => $therapist->id,
            'therapist_name' => $name,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
        ]);

        return ['therapist' => $therapist, 'bill' => $bill];
    }

    private function createPayment(User $therapist, TherapistBill $bill, string $paidAt, float $amount, PaymentMethod $method): TherapistBillPayment
    {
        return TherapistBillPayment::factory()->create([
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $bill->id,
            'paid_at' => $paidAt,
            'amount' => $amount,
            'method' => $method,
        ]);
    }

    public function test_pay_stub_report_requires_authentication(): void
    {
        $response = $this->get(route('admin.finance.pay-stub-report.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_pay_stub_report_requires_admin_role(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)->get(route('admin.finance.pay-stub-report.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_pay_stub_report_page(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.finance.pay-stub-report.index'));

        $response->assertOk();
        $response->assertSee('Pay Stub Report');
        $response->assertSee('Calendar Year');
    }

    public function test_index_shows_year_dropdown_with_current_year(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.finance.pay-stub-report.index'));

        $response->assertOk();
        $response->assertSee((string) date('Y'));
        $response->assertSee('2026');
    }

    public function test_data_endpoint_returns_therapists_with_payments(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $data = $this->createTherapistWithBill('Sally Jones PS', 70.00, '2026-01-01', '2026-01-15');
        $this->createPayment($data['therapist'], $data['bill'], '2026-01-20', 700.00, PaymentMethod::ACH);

        $response = $this->actingAs($admin)->post(route('admin.finance.pay-stub-report.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'filter_year' => 2026,
            'columns' => [],
            'order' => [],
            'search' => ['value' => ''],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);
        $responseData = $response->json();
        $this->assertGreaterThanOrEqual(1, $responseData['recordsTotal']);

        $found = false;
        foreach ($responseData['data'] as $row) {
            if (str_contains($row[0], 'Sally Jones PS')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected therapist Sally Jones PS in data');
    }

    public function test_data_endpoint_search_filters_by_name(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $data = $this->createTherapistWithBill('SearchableTherapist', 50.00, '2026-06-01', '2026-06-15');
        $this->createPayment($data['therapist'], $data['bill'], '2026-06-20', 500.00, PaymentMethod::ACH);

        $response = $this->actingAs($admin)->post(route('admin.finance.pay-stub-report.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'filter_year' => 2026,
            'columns' => [],
            'order' => [],
            'search' => ['value' => 'SearchableTherapist'],
        ]);

        $response->assertOk();
        $responseData = $response->json();
        $this->assertGreaterThanOrEqual(1, $responseData['recordsFiltered']);
    }

    public function test_download_returns_pdf_for_therapist_year(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $data = $this->createTherapistWithBill('Jane Doe PS', 55.00, '2026-02-01', '2026-02-15');
        $this->createPayment($data['therapist'], $data['bill'], '2026-02-16', 550.00, PaymentMethod::DIRECT_DEPOSIT);

        $response = $this->actingAs($admin)->get(route('admin.finance.pay-stub-report.download', [
            'therapist_id' => $data['therapist']->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_download_pdf_contains_all_payroll_periods_with_correct_ytd(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist = User::factory()->therapist()->create(['name' => 'Multi Period Therapist']);
        TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 60.00]);

        $billJan = TherapistBill::factory()->sent()->create([
            'therapist_id' => $therapist->id,
            'therapist_name' => 'Multi Period Therapist',
            'billing_period_start' => '2026-01-01',
            'billing_period_end' => '2026-01-31',
        ]);
        $billMar = TherapistBill::factory()->sent()->create([
            'therapist_id' => $therapist->id,
            'therapist_name' => 'Multi Period Therapist',
            'billing_period_start' => '2026-03-01',
            'billing_period_end' => '2026-03-31',
        ]);
        $billJun = TherapistBill::factory()->sent()->create([
            'therapist_id' => $therapist->id,
            'therapist_name' => 'Multi Period Therapist',
            'billing_period_start' => '2026-06-01',
            'billing_period_end' => '2026-06-30',
        ]);

        $this->createPayment($therapist, $billJan, '2026-02-05', 600.00, PaymentMethod::ACH);
        $this->createPayment($therapist, $billMar, '2026-04-03', 720.00, PaymentMethod::ACH);
        $this->createPayment($therapist, $billJun, '2026-07-01', 480.00, PaymentMethod::DIRECT_DEPOSIT);

        $response = $this->actingAs($admin)->get(route('admin.finance.pay-stub-report.download', [
            'therapist_id' => $therapist->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        // Verify filename contains therapist name slug, ID, and year
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertNotNull($contentDisposition);
        $this->assertStringContainsString('multi-period-therapist', $contentDisposition);
        $this->assertStringContainsString((string) $therapist->id, $contentDisposition);
        $this->assertStringContainsString('2026', $contentDisposition);

        // Verify all 3 payroll periods are reflected in the report data
        /** @var \App\Domain\Finance\Services\PayStubReportService $reportService */
        $reportService = app(\App\Domain\Finance\Services\PayStubReportService::class);
        $data = $reportService->getTherapistPayStubData($therapist->id, 2026);

        $this->assertCount(3, $data['rows']);
        $this->assertEquals('January 1 to January 31', $data['rows'][0]['payroll_period']);
        $this->assertEquals('March 1 to March 31', $data['rows'][1]['payroll_period']);
        $this->assertEquals('June 1 to June 30', $data['rows'][2]['payroll_period']);

        // Verify YTD cumulates correctly across periods
        $this->assertEquals(600.00, $data['rows'][0]['ytd_regular_pay']);
        $this->assertEquals(1320.00, $data['rows'][1]['ytd_regular_pay']);
        $this->assertEquals(1800.00, $data['rows'][2]['ytd_regular_pay']);
    }

    public function test_download_validates_required_parameters(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.finance.pay-stub-report.download'));

        $response->assertSessionHasErrors(['year', 'therapist_id']);
    }

    public function test_download_validates_therapist_exists(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.finance.pay-stub-report.download', [
            'therapist_id' => 99999,
            'year' => 2026,
        ]));

        $response->assertSessionHasErrors(['therapist_id']);
    }
}
