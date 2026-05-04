<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Services\TherapistBillPaymentService;
use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TherapistBillStatus;
use App\Models\Expense;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedExpenseLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_screen_redirects_to_show_for_linked_expense(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $expense = $this->createLinkedExpense($admin);

        $response = $this->actingAs($admin)
            ->get(route('admin.expenses.edit', $expense));

        $response->assertRedirect(route('admin.expenses.show', $expense));
    }

    public function test_policy_blocks_update_and_delete_on_linked_expense(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $expense = $this->createLinkedExpense($admin);

        $this->assertFalse($admin->can('update', $expense));
        $this->assertFalse($admin->can('delete', $expense));
    }

    public function test_policy_allows_update_and_delete_on_regular_expense(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $expense = Expense::factory()->create([
            'source_type' => null,
            'source_id' => null,
        ]);

        $this->assertTrue($admin->can('update', $expense));
        $this->assertTrue($admin->can('delete', $expense));
    }

    private function createLinkedExpense(User $admin): Expense
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT,
            'total_due' => 100.00,
        ]);

        $service = app(TherapistBillPaymentService::class);
        $dto = new RecordTherapistBillPaymentDTO(
            therapistBillId: $bill->id,
            paidAt: '2026-04-01',
            amount: 100.00,
            method: PaymentMethod::DIRECT_DEPOSIT,
            reference: null,
            notes: null,
            recordedById: $admin->id,
        );
        $payment = $service->recordPayment($dto);

        /** @var Expense $expense */
        $expense = Expense::forSource($payment)->firstOrFail();

        return $expense;
    }
}
