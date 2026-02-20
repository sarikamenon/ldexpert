<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_invoice_payments_index(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.payments.invoices.index'));

        $response->assertOk();
    }

    public function test_non_admin_cannot_view_invoice_payments_index(): void
    {
        $user = User::factory()->create(['role' => Role::THERAPIST]);

        $response = $this->actingAs($user)->get(route('admin.payments.invoices.index'));

        $response->assertForbidden();
    }
}
