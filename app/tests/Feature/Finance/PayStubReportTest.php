<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TherapistBillStatus;
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
        $data = $this->createTherapistWithBill('Sally Jones PS', 70.00, 'PST-FT-DATA-001', '2026-01-01', '2026-01-15', 700.00);
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
        $data = $this->createTherapistWithBill('SearchableTherapist', 50.00, 'PST-FT-SRCH-001', '2026-06-01', '2026-06-15', 500.00);
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
        $data = $this->createTherapistWithBill('Jane Doe PS', 55.00, 'PST-FT-PDF-001', '2026-02-01', '2026-02-15', 550.00);
        $this->createPayment($data['therapist'], $data['bill'], '2026-02-16', 550.00, PaymentMethod::DIRECT_DEPOSIT);

        $response = $this->actingAs($admin)->get(route('admin.finance.pay-stub-report.download', [
            'therapist_id' => $data['therapist']->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
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
