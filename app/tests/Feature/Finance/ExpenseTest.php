<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
        $this->category = ExpenseCategory::factory()->create();
    }

    public function test_admin_can_create_expense(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.expenses.store'),
            [
                'expense_category_id' => $this->category->id,
                'expense_date' => '2026-02-13',
                'amount' => 250.00,
                'vendor_payee' => 'Office Depot',
                'description' => 'Office supplies',
                'reference' => 'INV-123',
            ]
        );

        $response->assertRedirect(route('admin.expenses.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $this->category->id,
            'amount' => 250.00,
            'vendor_payee' => 'Office Depot',
        ]);
    }

    public function test_admin_can_view_expense(): void
    {
        $expense = Expense::factory()->create([
            'expense_category_id' => $this->category->id,
            'created_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.expenses.show', $expense)
        );

        $response->assertOk();
        $response->assertSee(number_format((float) $expense->amount, 2));
    }

    public function test_admin_can_update_expense(): void
    {
        $expense = Expense::factory()->create([
            'expense_category_id' => $this->category->id,
            'amount' => 100.00,
        ]);

        $response = $this->actingAs($this->admin)->put(
            route('admin.expenses.update', $expense),
            [
                'expense_category_id' => $this->category->id,
                'expense_date' => '2026-02-13',
                'amount' => 150.00,
                'vendor_payee' => 'Updated Vendor',
                'description' => 'Updated description',
            ]
        );

        $response->assertRedirect(route('admin.expenses.show', $expense));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => 150.00,
            'vendor_payee' => 'Updated Vendor',
        ]);
    }

    public function test_admin_can_delete_expense(): void
    {
        $expense = Expense::factory()->create([
            'expense_category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            route('admin.expenses.destroy', $expense)
        );

        $response->assertRedirect(route('admin.expenses.index'));
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_expense_amount_must_be_positive(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.expenses.store'),
            [
                'expense_category_id' => $this->category->id,
                'expense_date' => '2026-02-13',
                'amount' => 0,
            ]
        );

        $response->assertSessionHasErrors('amount');
    }

    public function test_expense_date_cannot_be_in_future(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.expenses.store'),
            [
                'expense_category_id' => $this->category->id,
                'expense_date' => now()->addDays(1)->format('Y-m-d'),
                'amount' => 100.00,
            ]
        );

        $response->assertSessionHasErrors('expense_date');
    }
}
