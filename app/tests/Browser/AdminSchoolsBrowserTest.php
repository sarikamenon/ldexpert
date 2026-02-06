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
