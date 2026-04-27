<?php

declare(strict_types=1);

namespace Tests\Browser\Admin\Billing;

use App\Enums\TherapistBillStatus;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class TherapistBillSendGuardTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+bill-send-guard@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_zero_amount_send_shows_actionable_dialog_with_expected_button_order(): void
    {
        $bill = TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
            'total_due' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->press('Send Bill')
                ->waitForText('Cannot send this bill')
                ->assertSee('This bill total is $0.00, so it cannot be sent. Add billable sessions or keep it as draft/delete it.')
                ->assertSee('Add or remove sessions')
                ->assertSee('Close')
                ->assertSee('Delete bill');

            $buttonOrder = $browser->script("return Array.from(document.querySelectorAll('.swal2-actions button')).map((button) => button.textContent.trim());");
            $this->assertSame(['Cancel', 'Add or remove sessions', 'Delete bill'], $buttonOrder[0] ?? []);
        });
    }

    public function test_zero_amount_send_dialog_can_navigate_to_attach_sessions(): void
    {
        $bill = TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
            'total_due' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->press('Send Bill')
                ->waitForText('Cannot send this bill')
                ->press('Add or remove sessions')
                ->waitForLocation(route('admin.billing.therapist-bills.attach-sessions', $bill, false));
        });
    }

    public function test_zero_amount_send_dialog_can_delete_bill_from_action_button(): void
    {
        $bill = TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
            'total_due' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->press('Send Bill')
                ->waitForText('Cannot send this bill')
                ->press('Delete bill')
                ->waitForText('Delete Bill?')
                ->press('Yes, delete bill')
                ->waitForLocation(route('admin.billing.therapist-bills.index', [], false))
                ->assertSee('Bill deleted successfully.');
        });

        $this->assertSoftDeleted('therapist_bills', ['id' => $bill->id]);
    }
}

