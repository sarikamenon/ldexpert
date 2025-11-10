<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TherapistStudentsBrowserTest extends DuskTestCase
{
    public function testTherapistCanNavigateStudentsPages(): void
    {
        $this->browse(function (Browser $browser) {
            $therapist = User::factory()->therapist()->create();

            $browser->loginAs($therapist)
                ->visit('/therapist/students')
                ->assertSee('My Students')
                ->visit('/therapist/students/create')
                ->assertSee('Add Student');
        });
    }

    public function testTherapistCanCreateStudentViaUI(): void
    {
        $this->browse(function (Browser $browser) {
            $therapist = User::factory()->therapist()->create();

            $browser->loginAs($therapist)
                ->visit('/therapist/students/create')
                ->type('name', 'UI Student')
                ->type('email', 'ui.student@example.com')
                ->type('password', 'Secret123!')
                ->press('Create Student')
                ->waitForLocation('/therapist/students')
                ->assertSee('Student created successfully.');
        });
    }
}
