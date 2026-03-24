<?php

declare(strict_types=1);

namespace Tests\Browser\Admin;

use App\Enums\ContractStatus;
use App\Enums\SchoolStatus;
use App\Enums\ServiceStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class StatusFilterDefaultBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+statusfilter@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    // ─── Services ────────────────────────────────────────────────────

    public function test_services_page_defaults_to_active_filter(): void
    {
        Service::factory()->create([
            'name' => 'ActiveServiceBrowser',
            'status' => ServiceStatus::ACTIVE->value,
        ]);
        Service::factory()->create([
            'name' => 'InactiveServiceBrowser',
            'status' => ServiceStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/services')
                ->waitForText('ActiveServiceBrowser')
                ->assertSee('ActiveServiceBrowser')
                ->assertDontSee('InactiveServiceBrowser');
        });
    }

    public function test_services_page_shows_all_when_all_selected(): void
    {
        Service::factory()->create([
            'name' => 'ActiveServiceAll',
            'status' => ServiceStatus::ACTIVE->value,
        ]);
        Service::factory()->create([
            'name' => 'InactiveServiceAll',
            'status' => ServiceStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/services')
                ->select('status', 'all')
                ->press('Filter')
                ->waitForText('InactiveServiceAll')
                ->assertSee('ActiveServiceAll')
                ->assertSee('InactiveServiceAll');
        });
    }

    public function test_services_clear_filter_resets_to_active(): void
    {
        Service::factory()->create([
            'name' => 'ActiveServiceClear',
            'status' => ServiceStatus::ACTIVE->value,
        ]);
        Service::factory()->create([
            'name' => 'InactiveServiceClear',
            'status' => ServiceStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/services')
                ->select('status', 'all')
                ->press('Filter')
                ->waitForText('InactiveServiceClear')
                ->press('Clear')
                ->waitUntilMissingText('InactiveServiceClear')
                ->assertSee('ActiveServiceClear')
                ->assertDontSee('InactiveServiceClear');
        });
    }

    // ─── Schools ─────────────────────────────────────────────────────

    public function test_schools_page_defaults_to_active_filter(): void
    {
        School::factory()->create([
            'display_name' => 'ActiveSchoolBrowser',
            'status' => SchoolStatus::ACTIVE->value,
        ]);
        School::factory()->create([
            'display_name' => 'InactiveSchoolBrowser',
            'status' => SchoolStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/schools')
                ->waitForText('ActiveSchoolBrowser')
                ->assertSee('ActiveSchoolBrowser')
                ->assertDontSee('InactiveSchoolBrowser');
        });
    }

    public function test_schools_page_shows_all_when_all_selected(): void
    {
        School::factory()->create([
            'display_name' => 'ActiveSchoolAll',
            'status' => SchoolStatus::ACTIVE->value,
        ]);
        School::factory()->create([
            'display_name' => 'InactiveSchoolAll',
            'status' => SchoolStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/schools')
                ->select('status', 'all')
                ->press('Filter')
                ->waitForText('InactiveSchoolAll')
                ->assertSee('ActiveSchoolAll')
                ->assertSee('InactiveSchoolAll');
        });
    }

    // ─── Therapists ──────────────────────────────────────────────────

    public function test_therapists_page_defaults_to_active_filter(): void
    {
        $manager = User::factory()->admin()->create();

        User::factory()->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'ActiveTherapistBrowser',
                'last_name' => 'Test',
                'manager_id' => $manager->id,
            ]), 'therapistProfile')
            ->create(['name' => 'ActiveTherapistBrowser', 'status' => 'active']);

        User::factory()->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'InactiveTherapistBrowser',
                'last_name' => 'Test',
                'manager_id' => $manager->id,
            ]), 'therapistProfile')
            ->create(['name' => 'InactiveTherapistBrowser', 'status' => 'inactive']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->waitForText('ActiveTherapistBrowser')
                ->assertSee('ActiveTherapistBrowser')
                ->assertDontSee('InactiveTherapistBrowser');
        });
    }

    public function test_therapists_page_shows_all_when_all_selected(): void
    {
        $manager = User::factory()->admin()->create();

        User::factory()->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'ActiveTherapistAll',
                'last_name' => 'Test',
                'manager_id' => $manager->id,
            ]), 'therapistProfile')
            ->create(['name' => 'ActiveTherapistAll', 'status' => 'active']);

        User::factory()->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'InactiveTherapistAll',
                'last_name' => 'Test',
                'manager_id' => $manager->id,
            ]), 'therapistProfile')
            ->create(['name' => 'InactiveTherapistAll', 'status' => 'inactive']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/therapists')
                ->select('status', 'all')
                ->press('Filter')
                ->waitForText('InactiveTherapistAll')
                ->assertSee('ActiveTherapistAll')
                ->assertSee('InactiveTherapistAll');
        });
    }

    // ─── Students ────────────────────────────────────────────────────

    public function test_students_page_defaults_to_active_filter(): void
    {
        $activeStudent = User::factory()->create([
            'name' => 'ActiveStudentBrowser',
            'role' => 'student',
            'status' => 'active',
        ]);
        StudentProfile::factory()->create([
            'user_id' => $activeStudent->id,
            'first_name' => 'ActiveStudentBrowser',
            'last_name' => 'Test',
        ]);

        $inactiveStudent = User::factory()->create([
            'name' => 'InactiveStudentBrowser',
            'role' => 'student',
            'status' => 'inactive',
        ]);
        StudentProfile::factory()->create([
            'user_id' => $inactiveStudent->id,
            'first_name' => 'InactiveStudentBrowser',
            'last_name' => 'Test',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->waitForText('ActiveStudentBrowser')
                ->assertSee('ActiveStudentBrowser')
                ->assertDontSee('InactiveStudentBrowser');
        });
    }

    public function test_students_page_shows_all_when_all_selected(): void
    {
        $activeStudent = User::factory()->create([
            'name' => 'ActiveStudentAll',
            'role' => 'student',
            'status' => 'active',
        ]);
        StudentProfile::factory()->create([
            'user_id' => $activeStudent->id,
            'first_name' => 'ActiveStudentAll',
            'last_name' => 'Test',
        ]);

        $inactiveStudent = User::factory()->create([
            'name' => 'InactiveStudentAll',
            'role' => 'student',
            'status' => 'inactive',
        ]);
        StudentProfile::factory()->create([
            'user_id' => $inactiveStudent->id,
            'first_name' => 'InactiveStudentAll',
            'last_name' => 'Test',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->select('status', 'all')
                ->press('Filter')
                ->waitForText('InactiveStudentAll')
                ->assertSee('ActiveStudentAll')
                ->assertSee('InactiveStudentAll');
        });
    }

    // ─── School Contracts ────────────────────────────────────────────

    public function test_school_contracts_page_defaults_to_active_filter(): void
    {
        $school = School::factory()->create();

        SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'status' => ContractStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/contracts/schools')
                ->waitFor('#schoolContractsTable tbody tr')
                ->assertSeeIn('#schoolContractsTable', 'Active')
                ->assertDontSeeIn('#schoolContractsTable', 'Inactive');
        });
    }

    public function test_school_contracts_page_shows_all_when_all_selected(): void
    {
        $school = School::factory()->create();

        SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'status' => ContractStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/contracts/schools')
                ->select('status', 'all')
                ->press('Filter')
                ->waitForText('Inactive')
                ->assertSeeIn('#schoolContractsTable', 'Active')
                ->assertSeeIn('#schoolContractsTable', 'Inactive');
        });
    }

    // ─── Therapist Contracts ─────────────────────────────────────────

    public function test_therapist_contracts_page_defaults_to_active_filter(): void
    {
        $profile = TherapistProfile::factory()->create();

        TherapistContract::create([
            'therapist_id' => $profile->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        TherapistContract::create([
            'therapist_id' => $profile->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'status' => ContractStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/contracts/therapists')
                ->waitFor('#therapistContractsTable tbody tr')
                ->assertSeeIn('#therapistContractsTable', 'Active')
                ->assertDontSeeIn('#therapistContractsTable', 'Inactive');
        });
    }

    public function test_therapist_contracts_page_shows_all_when_all_selected(): void
    {
        $profile = TherapistProfile::factory()->create();

        TherapistContract::create([
            'therapist_id' => $profile->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        TherapistContract::create([
            'therapist_id' => $profile->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'status' => ContractStatus::INACTIVE->value,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/contracts/therapists')
                ->select('status', 'all')
                ->press('Filter')
                ->waitForText('Inactive')
                ->assertSeeIn('#therapistContractsTable', 'Active')
                ->assertSeeIn('#therapistContractsTable', 'Inactive');
        });
    }
}
