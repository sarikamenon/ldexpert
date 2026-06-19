<?php

declare(strict_types=1);

use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Paystub Report ───────────────────────────────────────────────────────────

/**
 * Paystub test cases are pending formal TC IDs in app/qa/LD-Expert-QA.xlsx.
 * This file exists as a placeholder per the BrowserQA file structure contract.
 * Add TC-T026+ rows to the Therapist sheet and regenerate to expand coverage.
 */
it('paystub report page loads without errors for a therapist', function (): void {
    $admin     = User::factory()->admin()->qa()->create();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->loginAs($therapist)
            ->visit('/therapist/paystub')
            ->assertDontSee('Whoops')
            ->assertDontSee('500');
    });
});
