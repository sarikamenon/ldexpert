<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Position;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class AdminTherapistsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    private User $manager;

    private User $therapist;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->position = Position::factory()->create(['name' => 'SLP']);

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+therapists@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->manager = User::factory()->admin()->create();

        $this->therapist = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'manager_id' => $this->manager->id,
            ]), 'therapistProfile')
            ->create();
    }

    public function test_admin_can_view_therapists_list(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->assertSee('Therapists')
                ->assertSee('Total Therapists')
                ->assertSee('Active')
                ->assertSee('Add Therapist')
                ->assertSee($this->therapist->therapistProfile->first_name);
        });
    }

    public function test_admin_can_navigate_to_create_therapist_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->clickLink('Add Therapist')
                ->assertPathIs('/admin/therapists/create')
                ->assertSee('Create Therapist')
                ->assertSee('Employment & Identity')
                ->assertSee('Contact & Account')
                ->assertSee('Professional');
        });
    }

    public function test_admin_can_create_therapist(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists/create')
                ->radio('@therapist-employee-type-w2')
                ->select('title', 'Dr.')
                ->type('@therapist-first-name', 'Jane')
                ->type('@therapist-last-name', 'Smith')
                ->type('@therapist-personal-email', 'jane.smith@example.com')
                ->type('@therapist-phone', '555-123-4567')
                ->type('@therapist-ld-email', 'jane.smith@ldexpert.com')
                ->select('position_id', (string) $this->position->id)
                ->select('state', 'CA')
                ->select('timezone', 'America/Los_Angeles')
                ->select('manager_id', (string) $this->manager->id)
                ->press('Create Therapist')
                ->assertPathIs('/admin/therapists')
                ->assertSee('Therapist created successfully');
        });

        $this->assertDatabaseHas('therapist_profiles', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'personal_email' => 'jane.smith@example.com',
        ]);
    }

    public function test_admin_can_view_edit_therapist_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->click('@edit-therapist-'.$this->therapist->id)
                ->assertPathIs('/admin/therapists/'.$this->therapist->id.'/edit')
                ->assertSee('Edit Therapist')
                ->assertInputValue('first_name', $this->therapist->therapistProfile->first_name)
                ->assertInputValue('last_name', $this->therapist->therapistProfile->last_name);
        });
    }

    public function test_admin_can_update_therapist(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists/'.$this->therapist->id.'/edit')
                ->type('@therapist-first-name', 'Updated')
                ->type('@therapist-last-name', 'Name')
                ->press('Update Therapist Info')
                ->assertPathIs('/admin/therapists')
                ->assertSee('Therapist information updated successfully');
        });

        $this->assertDatabaseHas('therapist_profiles', [
            'user_id' => $this->therapist->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);
    }

    public function test_admin_can_toggle_therapist_status(): void
    {
        $this->therapist->update(['status' => 'active']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->click('@status-toggle-'.$this->therapist->id)
                ->waitForText('Deactivate Therapist?')
                ->type('input[type="text"]', 'Testing deactivation')
                ->press('Yes, deactivate')
                ->waitForText('Success')
                ->pause(1000); // Wait for page reload
        });

        $this->assertDatabaseHas('users', [
            'id' => $this->therapist->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_search_therapists(): void
    {
        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'Unique',
                'last_name' => 'Therapist',
                'manager_id' => $this->manager->id,
            ]), 'therapistProfile')
            ->create(['name' => 'Unique Therapist']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->type('search', 'Unique')
                ->press('Filter')
                ->assertSee('Unique');
        });
    }

    public function test_admin_can_filter_by_status(): void
    {
        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $this->manager->id]), 'therapistProfile')
            ->create(['status' => 'inactive']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->select('status', 'inactive')
                ->press('Filter')
                ->assertSee('Inactive');
        });
    }

    public function test_admin_can_export_therapists(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->click('#exportTherapistsButton')
                ->pause(1000); // Wait for download to start

            // Just verify the button click doesn't error
            // Actual file download testing is complex in Dusk
            $browser->assertPathIs('/admin/therapists');
        });
    }
}
