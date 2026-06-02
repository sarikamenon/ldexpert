<?php

declare(strict_types=1);

namespace Tests\Browser\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class InvoiceSendGuardTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+invoice-send-guard@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_zero_amount_send_shows_actionable_dialog_with_expected_button_order(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.invoices.show', $invoice))
                ->press('Send Invoice')
                ->waitForText('Cannot send this invoice')
                ->assertSee('This invoice total is $0.00, so it cannot be sent. Add billable sessions or keep it as draft.')
                ->assertSee('Add or remove sessions')
                ->assertSee('Close');

            // Only visible buttons carry text; SweetAlert keeps a hidden deny button in the DOM.
            $buttonOrder = $browser->script("return Array.from(document.querySelectorAll('.swal2-actions button')).map((button) => button.textContent.trim()).filter((label) => label !== '');");
            $this->assertSame(['Close', 'Add or remove sessions'], $buttonOrder[0] ?? []);
        });
    }

    public function test_zero_amount_send_dialog_can_navigate_to_attach_sessions(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.invoices.show', $invoice))
                ->press('Send Invoice')
                ->waitForText('Cannot send this invoice')
                // Click the dialog's confirm button specifically; the page header also
                // carries an "Add or remove sessions" button behind the dialog backdrop.
                ->click('.swal2-confirm')
                ->waitForLocation(route('admin.invoices.attach-sessions', $invoice, false));
        });
    }
}
