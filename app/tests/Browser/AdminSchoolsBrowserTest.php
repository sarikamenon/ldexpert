<?php

namespace Tests\Browser;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AdminSchoolsBrowserTest extends DuskTestCase
{
    private const PASSWORD = 'Password123!';

    public function testAdminCanNavigateSchoolsPages(): void
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

    public function testAdminCanSeeEditForm(): void
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
