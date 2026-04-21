<?php

declare(strict_types=1);

namespace Tests\Browser\Admin\Billing;

use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class TherapistBillPaymentDeleteTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+payment-delete@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_delete_button_shows_confirmation_dialog(): void
    {
        TherapistBillPayment::factory()->create([
            'recorded_by_id' => $this->admin->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.payments.therapist-bills.index'))
                ->waitFor('#therapistBillPaymentsTable tbody tr')
                ->within('#therapistBillPaymentsTable tbody tr:first-child', function (Browser $row) {
                    $row->click('button[aria-label="Delete Payment"]');
                })
                ->waitForText('Delete therapist bill payment?')
                ->assertSee('This will remove all allocations and the related ledger entry. This action cannot be undone.');
        });
    }

    public function test_admin_can_cancel_delete_and_payment_is_not_removed(): void
    {
        $payment = TherapistBillPayment::factory()->create([
            'recorded_by_id' => $this->admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($payment) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.payments.therapist-bills.index'))
                ->waitFor('#therapistBillPaymentsTable tbody tr')
                ->within('#therapistBillPaymentsTable tbody tr:first-child', function (Browser $row) {
                    $row->click('button[aria-label="Delete Payment"]');
                })
                ->waitForText('Delete therapist bill payment?')
                ->press('Cancel')
                ->waitUntilMissing('.swal2-container');

            $this->assertDatabaseHas('therapist_bill_payments', ['id' => $payment->id, 'deleted_at' => null]);
        });
    }

    public function test_admin_can_confirm_delete_and_payment_is_removed(): void
    {
        $payment = TherapistBillPayment::factory()->create([
            'recorded_by_id' => $this->admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($payment) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.payments.therapist-bills.index'))
                ->waitFor('#therapistBillPaymentsTable tbody tr')
                ->within('#therapistBillPaymentsTable tbody tr:first-child', function (Browser $row) {
                    $row->click('button[aria-label="Delete Payment"]');
                })
                ->waitForText('Delete therapist bill payment?')
                ->press('Yes, delete')
                ->waitUntilMissing('.swal2-container')
                ->pause(1000);
        });

        $this->assertSoftDeleted('therapist_bill_payments', ['id' => $payment->id]);
    }
}
