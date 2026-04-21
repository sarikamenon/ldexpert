<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_payouts_category_is_marked_protected(): void
    {
        $category = ExpenseCategory::findOrFail(10);

        $this->assertSame('therapist-payouts', $category->slug);
        $this->assertTrue($category->isProtected());
    }

    public function test_admin_cannot_deactivate_protected_category(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $category = ExpenseCategory::findOrFail(10);

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.settings.expense-categories.toggle-status', $category));

        $response->assertStatus(403);
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_policy_blocks_delete_on_protected_category(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $category = ExpenseCategory::findOrFail(10);

        $this->assertFalse($admin->can('delete', $category));
        $this->assertFalse($admin->can('toggleStatus', $category));
    }

    public function test_update_form_cannot_deactivate_protected_category(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $category = ExpenseCategory::findOrFail(10);

        $this->actingAs($admin)->put(
            route('admin.settings.expense-categories.update', $category),
            ['name' => 'Therapist Payouts', 'is_active' => 0]
        );

        $this->assertTrue($category->fresh()->is_active);
    }
}
