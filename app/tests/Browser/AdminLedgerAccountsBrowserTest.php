<?php

namespace Tests\Browser;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AdminLedgerAccountsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const PASSWORD = 'Password123!';

    public function test_admin_can_view_school_ledger_accounts_with_pagination(): void
    {
        $this->browse(function (Browser $browser) {
            $admin = $this->createAdminUser();

            School::factory()->count(30)->create();

            $this->loginThroughUi($browser, $admin);

            $browser->visit('/admin/ledger/accounts?type=schools')
                ->assertSee('Accounts Ledger')
                ->waitFor('#ledgerAccountsTable')
                ->waitFor('.dataTables_wrapper')
                ->assertPresent('#ledgerAccountsTable_paginate')
                ->within('#ledgerAccountsTable_paginate', function (Browser $pagination) {
                    $pagination->clickLink('Next');
                });
        });
    }

    public function test_admin_can_view_therapist_ledger_accounts_with_pagination(): void
    {
        $this->browse(function (Browser $browser) {
            $admin = $this->createAdminUser();

            User::factory()->count(30)->create([
                'role' => Role::THERAPIST,
            ]);

            $this->loginThroughUi($browser, $admin);

            $browser->visit('/admin/ledger/accounts?type=therapists')
                ->assertSee('Accounts Ledger')
                ->waitFor('#ledgerAccountsTable')
                ->waitFor('.dataTables_wrapper')
                ->assertPresent('#ledgerAccountsTable_paginate')
                ->within('#ledgerAccountsTable_paginate', function (Browser $pagination) {
                    $pagination->clickLink('Next');
                });
        });
    }

    private function createAdminUser(): User
    {
        return User::factory()->admin()->create([
            'email' => 'admin+ledger-'.Str::uuid().'@example.com',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function loginThroughUi(Browser $browser, User $admin): void
    {
        $browser->visit('/login')
            ->type('email', $admin->email)
            ->type('password', self::PASSWORD)
            ->press('@login-button')
            ->waitForLocation('/admin/dashboard');
    }
}

