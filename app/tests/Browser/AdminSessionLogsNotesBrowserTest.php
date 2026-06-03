<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class AdminSessionLogsNotesBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_can_expand_and_collapse_a_long_notes_cell(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin+session-notes@example.com',
            'password' => bcrypt('password'),
        ]);

        // Notes long enough to overflow the 2-line clamp in the capped column,
        // so the "Read more" toggle is revealed.
        SessionLog::factory()->submitted()->create([
            'notes' => str_repeat('The child engaged well and completed every task in the plan. ', 12),
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/session-logs')
                ->waitFor('[data-notes-cell]')
                ->waitFor('[data-notes-toggle]:not(.hidden)')
                ->assertSeeIn('[data-notes-toggle]', 'Read more')
                ->assertAttribute('[data-notes-toggle]', 'aria-expanded', 'false')
                ->click('[data-notes-toggle]')
                ->waitForTextIn('[data-notes-toggle]', 'Read less')
                ->assertAttribute('[data-notes-toggle]', 'aria-expanded', 'true')
                ->assertPresent('[data-notes-text].notes-expanded')
                ->click('[data-notes-toggle]')
                ->waitForTextIn('[data-notes-toggle]', 'Read more')
                ->assertAttribute('[data-notes-toggle]', 'aria-expanded', 'false')
                ->assertMissing('[data-notes-text].notes-expanded');
        });
    }

    public function test_toggle_is_keyboard_operable(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin+session-notes-kbd@example.com',
            'password' => bcrypt('password'),
        ]);

        SessionLog::factory()->submitted()->create([
            'notes' => str_repeat('Detailed progress notes for the keyboard navigation test. ', 12),
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/session-logs')
                ->waitFor('[data-notes-toggle]:not(.hidden)')
                ->keys('[data-notes-toggle]', '{enter}')
                ->waitForTextIn('[data-notes-toggle]', 'Read less')
                ->assertAttribute('[data-notes-toggle]', 'aria-expanded', 'true');
        });
    }
}
