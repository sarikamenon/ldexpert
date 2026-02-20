<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLedgerAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_school_accounts_table(): void
    {
        $admin = User::factory()->admin()->create();
        School::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/ledger/accounts?type=schools');

        $response
            ->assertOk()
            ->assertSee('Accounts Ledger')
            ->assertSee('School Accounts (AR)')
            ->assertSee('Therapist Accounts (AP)')
            ->assertSee('Total Accounts')
            ->assertSee('Transactions');
    }

    public function test_index_displays_therapist_accounts_table(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create([
            'role' => Role::THERAPIST,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/ledger/accounts?type=therapists');

        $response
            ->assertOk()
            ->assertSee('Accounts Ledger')
            ->assertSee('School Accounts (AR)')
            ->assertSee('Therapist Accounts (AP)')
            ->assertSee('Total Accounts')
            ->assertSee('Transactions');
    }
}
