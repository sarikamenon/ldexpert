<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TherapistBillStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\School;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_all_time_totals_for_collected_and_paid(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $school = School::factory()->create();
        $therapist = User::factory()->create();

        $oldMonth = CarbonImmutable::now()->subMonths(2)->startOfMonth();

        $recentInvoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'status' => InvoiceStatus::SENT,
            'invoice_date' => now()->startOfMonth(),
            'total' => 100,
        ]);

        $oldInvoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'status' => InvoiceStatus::SENT,
            'invoice_date' => $oldMonth,
            'total' => 65,
        ]);

        InvoicePayment::factory()->create([
            'school_id' => $school->id,
            'invoice_id' => $oldInvoice->id,
            'paid_at' => $oldMonth->copy()->addDay(),
            'amount' => 65,
            'method' => PaymentMethod::CASH,
        ]);

        $recentBill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT,
            'bill_date' => now()->startOfMonth(),
            'total_due' => 40,
        ]);

        $oldBill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT,
            'bill_date' => $oldMonth,
            'total_due' => 30,
        ]);

        TherapistBillPayment::factory()->create([
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $oldBill->id,
            'paid_at' => $oldMonth->copy()->addDays(2),
            'amount' => 30,
            'method' => PaymentMethod::CASH,
        ]);

        Expense::factory()->create([
            'expense_date' => $oldMonth->copy()->addDays(3),
            'amount' => 15,
        ]);

        $response = $this->get('/admin/finance/dashboard');

        $response->assertOk();

        // Revenue collected should include old payments.
        $response->assertSee('$65.00', false);

        // 'Paid to Therapists' card and 'Recent Payments Made' block are gone —
        // therapist payouts now flow through the expense module.
        $response->assertDontSee('Paid to Therapists', false);
        $response->assertDontSee('Recent Payments Made', false);

        // Total expenses reflects only rows in the expenses table (no double-counting
        // of therapist payments, which are seeded via factory without the auto-expense).
        $response->assertSee('$15.00', false);

        // Net income = revenue collected - total expenses = 65 - 15 = 50.
        $response->assertSee('$50.00', false);
    }
}
