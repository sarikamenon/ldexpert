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

class IrsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_irs_report_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.finance.irs-report.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_irs_report_index_requires_admin_role(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)->get(route('admin.finance.irs-report.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_irs_report_page(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.finance.irs-report.index'));

        $response->assertOk();
        $response->assertSee('IRS Report');
        $response->assertSee('Apply Filters');
        $response->assertSee('Export CSV');
    }

    public function test_irs_report_shows_empty_state_when_no_date_range(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.finance.irs-report.index'));

        $response->assertOk();
        $response->assertSee('No payment records found');
    }

    public function test_irs_report_shows_rows_when_payments_in_date_range(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist = User::factory()->therapist()->create(['name' => 'Sally Jones']);
        TherapistProfile::factory()->create([
            'user_id' => $therapist->id,
            'hourly_rate' => 70.00,
        ]);
        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'therapist_name' => 'Sally Jones',
            'status' => TherapistBillStatus::SENT,
            'billing_period_start' => '2026-01-01',
            'billing_period_end' => '2026-01-15',
            'total_due' => 700.00,
        ]);
        TherapistBillPayment::factory()->create([
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $bill->id,
            'paid_at' => '2026-01-20',
            'amount' => 700.00,
            'method' => PaymentMethod::ACH,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.irs-report.index', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]));

        $response->assertOk();
        $response->assertSee('Sally Jones');
        $response->assertSee('700.00');
        $response->assertSee('70.00');
    }

    public function test_irs_report_export_returns_csv_with_headers_and_rows(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist = User::factory()->therapist()->create(['name' => 'Jane Doe']);
        TherapistProfile::factory()->create([
            'user_id' => $therapist->id,
            'hourly_rate' => 55.00,
        ]);
        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'therapist_name' => 'Jane Doe',
            'status' => TherapistBillStatus::SENT,
            'billing_period_start' => '2026-02-01',
            'billing_period_end' => '2026-02-15',
            'total_due' => 550.00,
        ]);
        TherapistBillPayment::factory()->create([
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $bill->id,
            'paid_at' => '2026-02-16',
            'amount' => 550.00,
            'method' => PaymentMethod::DIRECT_DEPOSIT,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.irs-report.export', [
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('RECIPIENT', $csv);
        $this->assertStringContainsString('PAYMENT DATE', $csv);
        $this->assertStringContainsString('PAYMENTS', $csv);
        $this->assertStringContainsString('DEDUCTIONS', $csv);
        $this->assertStringContainsString('YEAR-TO-DATE', $csv);
        $this->assertStringContainsString('TOTAL GROSS PAY', $csv);
        $this->assertStringContainsString('TOTAL DEDUCTIONS', $csv);
        $this->assertStringContainsString('TOTAL NET PAY', $csv);
        $this->assertStringContainsString('Jane Doe', $csv);
        $this->assertStringContainsString('550.00', $csv);
    }

    public function test_irs_report_filter_by_therapist_ids(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist1 = User::factory()->therapist()->create(['name' => 'Therapist One']);
        $therapist2 = User::factory()->therapist()->create(['name' => 'Therapist Two']);
        TherapistProfile::factory()->create(['user_id' => $therapist1->id, 'hourly_rate' => 50]);
        TherapistProfile::factory()->create(['user_id' => $therapist2->id, 'hourly_rate' => 60]);

        $bill1 = TherapistBill::factory()->create([
            'therapist_id' => $therapist1->id,
            'therapist_name' => 'Therapist One',
            'status' => TherapistBillStatus::SENT,
            'billing_period_start' => '2026-01-01',
            'billing_period_end' => '2026-01-15',
            'total_due' => 500,
        ]);
        $bill2 = TherapistBill::factory()->create([
            'therapist_id' => $therapist2->id,
            'therapist_name' => 'Therapist Two',
            'status' => TherapistBillStatus::SENT,
            'billing_period_start' => '2026-01-01',
            'billing_period_end' => '2026-01-15',
            'total_due' => 600,
        ]);
        TherapistBillPayment::factory()->create([
            'therapist_id' => $therapist1->id,
            'therapist_bill_id' => $bill1->id,
            'paid_at' => '2026-01-20',
            'amount' => 500,
            'method' => PaymentMethod::CASH,
        ]);
        TherapistBillPayment::factory()->create([
            'therapist_id' => $therapist2->id,
            'therapist_bill_id' => $bill2->id,
            'paid_at' => '2026-01-20',
            'amount' => 600,
            'method' => PaymentMethod::CASH,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.irs-report.index', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'therapist_ids' => [$therapist1->id],
        ]));

        $response->assertOk();
        $response->assertSee('Therapist One');
        $response->assertSee('500.00');
        $response->assertDontSee('600.00');
    }
}
