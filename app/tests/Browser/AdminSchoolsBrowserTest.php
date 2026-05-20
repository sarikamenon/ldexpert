<?php

namespace Tests\Browser;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AdminSchoolsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Override to run migrate:fresh only — skip migrate:rollback which hits broken down() methods.
     *
     * TODO: Remove this override once historical migrations have idempotent down() methods.
     * Known broken culprits include:
     *   - 2026_03_19_100005_add_advance_billing_columns_to_invoices_table (dropForeign on cols already removed by a later migration)
     *   - 2026_02_20_100000_convert_position_to_position_id_on_therapist_profiles (unguarded dropForeign)
     *   - 2026_03_06_100003_add_gateway_fields_to_invoice_payments_table (unguarded dropForeign)
     *   - 2025_11_20_034143_rename_service_columns_and_change_frequency_to_boolean (lossy data transform)
     * Estimated fix effort: ~2–3 hours to guard ~25 migrations with hasColumn() / foreignKeyExists().
     */
    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
    }

    private const PASSWORD = 'Password123!';

    public function test_admin_can_navigate_schools_pages(): void
    {
        $this->browse(function (Browser $browser) {
            $admin = $this->createAdminUser();
            School::factory()->create();

            $this->loginThroughUi($browser, $admin);

            $browser->visit('/admin/schools')
                ->assertSee('Schools')
                ->clickLink('Add School')
                ->assertSee('Add School');
        });
    }

    public function test_admin_can_see_edit_form(): void
    {
        $this->browse(function (Browser $browser) {
            $admin = $this->createAdminUser();
            $school = School::factory()->create();

            $this->loginThroughUi($browser, $admin);

            $browser->visit(route('admin.schools.edit', $school))
                ->assertSee('Edit School')
                ->assertInputValue('display_name', $school->display_name);
        });
    }

    public function test_admin_can_toggle_allow_weekend_scheduling(): void
    {
        $this->browse(function (Browser $browser) {
            $admin = $this->createAdminUser();
            $school = School::factory()->create([
                'allow_weekend_scheduling' => false,
                'contact_email' => 'school-contact@gmail.com',
                'invoice_email' => 'school-invoice@gmail.com',
            ]);

            $browser->loginAs($admin)
                ->visit(route('admin.schools.edit', $school))
                ->assertSee('Allow Saturday/Sunday scheduling')
                ->check('allow_weekend_scheduling')
                ->press('Update School/Family')
                ->waitForLocation(route('admin.schools.index', [], false));

            $school->refresh();
            $this->assertTrue($school->allow_weekend_scheduling);
        });
    }

    public function test_school_overview_shows_weekend_scheduling_row(): void
    {
        $this->browse(function (Browser $browser) {
            $admin = $this->createAdminUser();
            $school = School::factory()->create(['allow_weekend_scheduling' => true]);

            $browser->loginAs($admin)
                ->visit(route('admin.schools.show', [$school, 'tab' => 'overview']))
                ->assertSee('Allow weekend scheduling?')
                ->assertSee('Yes');
        });
    }

    private function createAdminUser(): User
    {
        return User::factory()->admin()->create([
            'email' => 'admin+schools-'.Str::uuid().'@example.com',
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
