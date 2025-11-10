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
            $browser->visit('/login')
                ->assertPathIs('/login')
                ->waitFor('input[name="email"]', 10)
                ->assertPresent('input[name="email"]')
                ->assertPresent('input[name="password"]')
                ->type('input[name="email"]', $user->email)
                ->type('input[name="password"]', $password)
                ->press('button[type="submit"]')
                ->assertPathIs('/dashboard')
                ->assertSee('Welcome back');
        });
    }
}
