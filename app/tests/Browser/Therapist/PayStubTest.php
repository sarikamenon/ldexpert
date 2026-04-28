<?php

declare(strict_types=1);

namespace Tests\Browser\Therapist;

use App\Models\TherapistBillPayment;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class PayStubTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->therapist = User::factory()->therapist()->create([
            'email' => 'therapist+paystub@example.com',
            'password' => bcrypt('password'),
        ]);

        TherapistProfile::factory()->create([
            'user_id' => $this->therapist->id,
            'hourly_rate' => 50.00,
        ]);
    }

    public function test_therapist_sees_empty_state_when_no_payments(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.finance.pay-stub.index'))
                ->assertSee('My Pay Stubs')
                ->assertSee('No pay stubs found');
        });
    }

    public function test_therapist_sees_year_row_with_payment_count_and_total(): void
    {
        TherapistBillPayment::factory()->create([
            'therapist_id' => $this->therapist->id,
            'paid_at' => '2026-02-01',
            'amount' => 300.00,
        ]);
        TherapistBillPayment::factory()->create([
            'therapist_id' => $this->therapist->id,
            'paid_at' => '2026-03-01',
            'amount' => 200.00,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.finance.pay-stub.index'))
                ->assertSee('My Pay Stubs')
                ->assertSee('2026')
                ->assertSee('2')
                ->assertSee('$500.00');
        });
    }

    public function test_therapist_sees_multiple_year_rows(): void
    {
        TherapistBillPayment::factory()->create([
            'therapist_id' => $this->therapist->id,
            'paid_at' => '2025-06-10',
            'amount' => 150.00,
        ]);
        TherapistBillPayment::factory()->create([
            'therapist_id' => $this->therapist->id,
            'paid_at' => '2026-01-20',
            'amount' => 250.00,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.finance.pay-stub.index'))
                ->assertSee('2026')
                ->assertSee('2025');
        });
    }

    public function test_download_button_triggers_pdf_download(): void
    {
        TherapistBillPayment::factory()->create([
            'therapist_id' => $this->therapist->id,
            'paid_at' => '2026-02-01',
            'amount' => 400.00,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.finance.pay-stub.index'))
                ->assertSee('2026')
                ->assertPresent('a[aria-label="Download 2026 pay stub as PDF"]');
        });
    }
}
