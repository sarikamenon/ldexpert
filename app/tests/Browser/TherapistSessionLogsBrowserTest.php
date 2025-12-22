<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class TherapistSessionLogsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_therapist_can_open_session_logs_pages(): void
    {
        $therapist = User::factory()->therapist()->create([
            'email' => 'therapist+sessionlogs@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browse(function (Browser $browser) use ($therapist) {
            $browser->loginAs($therapist)
                ->visit('/therapist/session-logs')
                ->assertPathIs('/therapist/session-logs')
                ->assertSee('Session Logs')
                ->visit('/therapist/session-logs/create')
                ->assertPathIs('/therapist/session-logs/create')
                ->assertSee('Create Session Log');
        });
    }
}
