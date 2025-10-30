<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    /** @test */
    public function user_can_login_via_login_form(): void
    {
        $password = 'Secret123!';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->browse(function (Browser $browser) use ($user, $password) {
            $browser->visit('/logout') // ensure guest
                ->visit('/login')
                ->waitFor('input[name="email"]', 5)
                ->type('email', $user->email)
                ->type('password', $password)
                ->press('LOG IN')
                ->assertPathIs('/dashboard')
                ->assertSee('Welcome back');
        });
    }
}
