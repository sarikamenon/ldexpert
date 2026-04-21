<?php

declare(strict_types=1);

namespace Tests\Browser\Admin\Billing;

use App\Enums\TherapistBillStatus;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class TherapistBillDeleteTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+bill-delete@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_delete_button_shows_confirmation_on_show_page(): void
    {
        $bill = TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
        ]);

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->assertSee('Delete Bill')
                ->press('Delete Bill')
                ->waitForText('Delete Bill?')
                ->assertSee('This will unlink all sessions and permanently delete the bill.');
        });
    }

    public function test_admin_can_cancel_delete_on_show_page(): void
    {
        $bill = TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
        ]);

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->press('Delete Bill')
                ->waitForText('Delete Bill?')
                ->press('Cancel')
                ->waitUntilMissing('.swal2-container');

            $this->assertDatabaseHas('therapist_bills', ['id' => $bill->id, 'deleted_at' => null]);
        });
    }

    public function test_admin_can_delete_draft_bill_from_show_page(): void
    {
        $bill = TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
        ]);

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->press('Delete Bill')
                ->waitForText('Delete Bill?')
                ->press('Yes, delete bill')
                ->waitForLocation(route('admin.billing.therapist-bills.index', [], false))
                ->assertSee('Bill deleted successfully.');
        });

        $this->assertSoftDeleted('therapist_bills', ['id' => $bill->id]);
    }

    public function test_admin_can_delete_sent_bill_from_show_page(): void
    {
        $bill = TherapistBill::factory()->sent($this->admin)->create();

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->press('Delete Bill')
                ->waitForText('Delete Bill?')
                ->press('Yes, delete bill')
                ->waitForLocation(route('admin.billing.therapist-bills.index', [], false))
                ->assertSee('Bill deleted successfully.');
        });

        $this->assertSoftDeleted('therapist_bills', ['id' => $bill->id]);
    }

    public function test_delete_button_not_visible_for_paid_bill(): void
    {
        $bill = TherapistBill::factory()->paid()->create();

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.show', $bill))
                ->assertDontSee('Delete Bill');
        });
    }

    public function test_delete_button_shows_confirmation_on_index_datatable(): void
    {
        TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
            'therapist_name' => 'DuskTherapist',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.index'))
                ->waitFor('#therapistBillsTable tbody tr')
                ->waitForText('DuskTherapist')
                ->within('#therapistBillsTable tbody tr:first-child', function (Browser $row) {
                    $row->click('button[aria-label="Delete Bill"]');
                })
                ->waitForText('Delete Bill?')
                ->assertSee('This will unlink all sessions and remove the bill.');
        });
    }

    public function test_admin_can_delete_bill_from_index_datatable(): void
    {
        $bill = TherapistBill::factory()->create([
            'status' => TherapistBillStatus::DRAFT->value,
            'therapist_name' => 'DuskTherapist',
        ]);

        $this->browse(function (Browser $browser) use ($bill) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.billing.therapist-bills.index'))
                ->waitFor('#therapistBillsTable tbody tr')
                ->waitForText('DuskTherapist')
                ->within('#therapistBillsTable tbody tr:first-child', function (Browser $row) {
                    $row->click('button[aria-label="Delete Bill"]');
                })
                ->waitForText('Delete Bill?')
                ->press('Yes, delete bill')
                ->waitFor('#therapistBillsTable tbody tr')
                ->assertDontSee($bill->bill_number);
        });

        $this->assertSoftDeleted('therapist_bills', ['id' => $bill->id]);
    }
}
