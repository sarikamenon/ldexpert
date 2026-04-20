<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\Position;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class AdminSSAAssignTherapistBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    private User $therapist;

    private ServiceSupportAgreement $unassignedSsa;

    private ServiceSupportAgreement $assignedSsa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+ssa-assign@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->therapist = User::factory()->create([
            'role' => Role::THERAPIST,
            'status' => UserStatus::ACTIVE,
            'name' => 'Dr. Alice Therapist',
        ]);

        $service = Service::factory()->create(['name' => 'Speech Therapy']);

        // Wire therapist profile → position → service so the AJAX endpoint returns this therapist
        $position = Position::factory()->create(['name' => 'SLP', 'status' => 'active']);
        $position->services()->attach($service->id);
        TherapistProfile::factory()->create([
            'user_id' => $this->therapist->id,
            'position_id' => $position->id,
            'manager_id' => $this->admin->id,
        ]);

        $this->unassignedSsa = ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => null,
            'status' => SSAStatus::PENDING->value,
            'primary_service_id' => $service->id,
        ]);

        $this->assignedSsa = ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => $this->therapist->id,
            'status' => SSAStatus::ACTIVE->value,
            'primary_service_id' => $service->id,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Visit the SSA list filtered to pending and wait for DataTables to render. */
    private function visitPendingList(Browser $browser): Browser
    {
        return $browser
            ->loginAs($this->admin)
            ->visit('/admin/ssas?filter_status=pending')
            ->pause(3000); // wait for DataTables AJAX to finish rendering rows
    }

    // ── List page ─────────────────────────────────────────────────────────────

    public function test_assign_therapist_icon_appears_in_actions_for_unassigned_ssa(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitPendingList($browser)
                ->assertPresent('.assign-therapist-btn');
        });
    }

    public function test_assign_therapist_icon_does_not_appear_for_assigned_ssa(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/ssas/'.$this->assignedSsa->id)
                ->assertDontSee('Assign Therapist');
        });
    }

    public function test_clicking_assign_icon_opens_modal_with_ssa_context(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitPendingList($browser)
                ->click('.assign-therapist-btn')
                ->waitFor('#assignTherapistModal.flex', 10)
                ->assertSee('Assign Therapist')
                ->assertPresent('#assignTherapistSelect');
        });
    }

    public function test_assign_modal_can_be_dismissed_with_cancel(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitPendingList($browser)
                ->click('.assign-therapist-btn')
                ->waitFor('#assignTherapistModal.flex', 10)
                ->click('#assignModalCancel')
                ->waitUntilMissing('#assignTherapistModal.flex', 5)
                ->assertNotPresent('#assignTherapistModal.flex');
        });
    }

    public function test_assign_modal_can_be_dismissed_with_close_button(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitPendingList($browser)
                ->click('.assign-therapist-btn')
                ->waitFor('#assignTherapistModal.flex', 10)
                ->click('#assignModalClose')
                ->waitUntilMissing('#assignTherapistModal.flex', 5)
                ->assertNotPresent('#assignTherapistModal.flex');
        });
    }

    public function test_assign_button_shows_error_when_no_therapist_selected(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitPendingList($browser)
                ->click('.assign-therapist-btn')
                ->waitFor('#assignTherapistModal.flex', 10)
                // Wait for therapist options to load (select populated by AJAX)
                ->pause(2000)
                ->select('#assignTherapistSelect', '')
                ->click('#assignModalConfirm')
                ->assertSeeIn('#assignTherapistError', 'Please select a therapist');
        });
    }

    public function test_admin_can_assign_therapist_from_list_page(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitPendingList($browser)
                ->click('.assign-therapist-btn')
                ->waitFor('#assignTherapistModal.flex', 10)
                ->pause(2000) // wait for therapist select to populate via AJAX
                ->select('#assignTherapistSelect', (string) $this->therapist->id)
                ->click('#assignModalConfirm')
                ->waitUntilMissing('#assignTherapistModal.flex', 10)
                ->waitForText('assigned successfully', 10);
        });

        $this->assertDatabaseHas('service_support_agreements', [
            'id' => $this->unassignedSsa->id,
            'assigned_therapist_id' => $this->therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('ssa_assignment_history', [
            'ssa_id' => $this->unassignedSsa->id,
            'therapist_id' => $this->therapist->id,
            'action' => 'assigned',
        ]);
    }

    // ── Show page ─────────────────────────────────────────────────────────────

    public function test_assign_therapist_button_visible_in_show_page_header_for_unassigned_ssa(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/ssas/'.$this->unassignedSsa->id)
                ->assertPresent('.assign-therapist-btn')
                ->assertSee('Assign Therapist');
        });
    }

    public function test_admin_can_assign_therapist_from_show_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/ssas/'.$this->unassignedSsa->id)
                ->click('.assign-therapist-btn')
                ->waitFor('#assignTherapistModal.flex', 10)
                ->pause(2000) // wait for therapist select to populate via AJAX
                ->select('#assignTherapistSelect', (string) $this->therapist->id)
                ->click('#assignModalConfirm')
                ->waitUntilMissing('#assignTherapistModal.flex', 10)
                ->waitForText('assigned successfully', 10);
        });

        $this->assertDatabaseHas('service_support_agreements', [
            'id' => $this->unassignedSsa->id,
            'assigned_therapist_id' => $this->therapist->id,
        ]);
    }

    public function test_admin_can_unassign_therapist_from_show_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/ssas/'.$this->assignedSsa->id.'?tab=assignment')
                ->waitFor('.unassign-therapist-btn', 5)
                ->click('.unassign-therapist-btn')
                ->waitFor('#unassignTherapistModal.flex', 5)
                ->assertSee('Unassign Therapist')
                ->assertSee($this->therapist->name)
                ->type('#unassignReasonInput', 'Therapist on leave')
                ->click('#unassignModalConfirm')
                ->waitUntilMissing('#unassignTherapistModal.flex', 10)
                ->waitForText('unassigned successfully', 10);
        });

        $this->assertDatabaseHas('service_support_agreements', [
            'id' => $this->assignedSsa->id,
            'assigned_therapist_id' => null,
            'status' => SSAStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas('ssa_assignment_history', [
            'ssa_id' => $this->assignedSsa->id,
            'therapist_id' => $this->therapist->id,
            'action' => 'unassigned',
            'reason' => 'Therapist on leave',
        ]);
    }

    public function test_unassign_modal_can_be_dismissed_with_cancel(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/ssas/'.$this->assignedSsa->id.'?tab=assignment')
                ->waitFor('.unassign-therapist-btn', 5)
                ->click('.unassign-therapist-btn')
                ->waitFor('#unassignTherapistModal.flex', 5)
                ->click('#unassignModalCancel')
                ->waitUntilMissing('#unassignTherapistModal.flex', 5)
                ->assertNotPresent('#unassignTherapistModal.flex');
        });

        $this->assertDatabaseHas('service_support_agreements', [
            'id' => $this->assignedSsa->id,
            'assigned_therapist_id' => $this->therapist->id,
        ]);
    }
}
