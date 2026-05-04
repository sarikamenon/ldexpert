<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\RateType;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogAdminOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_override_session_log_rates(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), [
                'therapist_rate_type' => RateType::FLAT->value,
                'therapist_rate_amount' => 200.00,
                'therapist_billable_amount' => 200.00,
                'school_rate_type' => RateType::HOURLY->value,
                'school_rate_amount' => 250.00,
                'school_invoice_amount' => 250.00,
                'is_rate_override' => true,
                'override_reason' => 'Admin override for reconciliation purposes',
            ]);

        $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
        $sessionLog->refresh();
        $this->assertTrue($sessionLog->is_rate_override);
        // Backend recalculates from rate + duration: FLAT 200 -> 200, HOURLY 250 with 60 min -> 250
        $this->assertSame(200.00, (float) $sessionLog->therapist_billable_amount);
        $this->assertSame(250.00, (float) $sessionLog->school_invoice_amount);
    }

    public function test_admin_override_recalculates_billable_amount_from_rate_and_duration(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create([
            'duration_minutes' => 60,
        ]);

        // Send rate type/amount; send wrong billable/invoice amounts – backend should recalculate from rate + duration
        $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), [
                'therapist_rate_type' => RateType::FLAT->value,
                'therapist_rate_amount' => 100.00,
                'therapist_billable_amount' => 999.00,
                'school_rate_type' => RateType::HOURLY->value,
                'school_rate_amount' => 120.00,
                'school_invoice_amount' => 999.00,
                'is_rate_override' => true,
                'override_reason' => 'Recalc test: backend must overwrite amounts from rate and duration',
            ])
            ->assertRedirect(route('admin.session-logs.show', $sessionLog));

        $sessionLog->refresh();
        $this->assertSame(100.00, (float) $sessionLog->therapist_billable_amount);
        $this->assertSame(120.00, (float) $sessionLog->school_invoice_amount);
    }

    public function test_admin_cannot_override_approved_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->approved()->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), [
                'therapist_rate_type' => RateType::FLAT->value,
                'therapist_rate_amount' => 200.00,
                'therapist_billable_amount' => 200.00,
                'school_rate_type' => RateType::FLAT->value,
                'school_rate_amount' => 250.00,
                'school_invoice_amount' => 250.00,
                'is_rate_override' => true,
                'override_reason' => 'Test override',
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_admin_override_requires_reason_when_override_enabled(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), [
                'therapist_rate_type' => RateType::FLAT->value,
                'therapist_rate_amount' => 200.00,
                'therapist_billable_amount' => 200.00,
                'school_rate_type' => RateType::HOURLY->value,
                'school_rate_amount' => 250.00,
                'school_invoice_amount' => 250.00,
                'is_rate_override' => true,
                'override_reason' => '', // Empty reason
            ]);

        $response->assertSessionHasErrors(['override_reason']);
    }

    public function test_admin_cannot_override_rates_when_session_attached_to_invoice(): void
    {
        $admin = User::factory()->admin()->create();
        $school = School::factory()->create();
        $invoice = Invoice::factory()->create(['school_id' => $school->id]);
        $sessionLog = SessionLog::factory()->submitted()->create([
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), [
                'therapist_rate_type' => RateType::FLAT->value,
                'therapist_rate_amount' => 200.00,
                'therapist_billable_amount' => 200.00,
                'school_rate_type' => RateType::HOURLY->value,
                'school_rate_amount' => 250.00,
                'school_invoice_amount' => 250.00,
                'is_rate_override' => true,
                'override_reason' => 'Attempting override while session is on an invoice',
            ]);

        $response->assertSessionHasErrors(['rate_override']);
        $this->assertSame(
            SessionLog::RATE_OVERRIDE_BLOCKED_MESSAGE,
            (string) session('errors')->first('rate_override')
        );
    }

    public function test_admin_cannot_override_rates_when_session_attached_to_therapist_bill(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $bill = TherapistBill::factory()->create(['therapist_id' => $therapist->id]);
        $sessionLog = SessionLog::factory()->submitted()->create([
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $bill->id,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), [
                'therapist_rate_type' => RateType::FLAT->value,
                'therapist_rate_amount' => 200.00,
                'therapist_billable_amount' => 200.00,
                'school_rate_type' => RateType::HOURLY->value,
                'school_rate_amount' => 250.00,
                'school_invoice_amount' => 250.00,
                'is_rate_override' => true,
                'override_reason' => 'Attempting override while session is on therapist bill',
            ]);

        $response->assertSessionHasErrors(['rate_override']);
        $this->assertSame(
            SessionLog::RATE_OVERRIDE_BLOCKED_MESSAGE,
            (string) session('errors')->first('rate_override')
        );
    }

    public function test_admin_cannot_override_rates_when_attached_even_if_is_rate_override_omitted(): void
    {
        $admin = User::factory()->admin()->create();
        $school = School::factory()->create();
        $invoice = Invoice::factory()->create(['school_id' => $school->id]);
        $sessionLog = SessionLog::factory()->submitted()->create([
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
        ]);

        $payload = [
            'therapist_rate_type' => RateType::FLAT->value,
            'therapist_rate_amount' => 200.00,
            'therapist_billable_amount' => 200.00,
            'school_rate_type' => RateType::HOURLY->value,
            'school_rate_amount' => 250.00,
            'school_invoice_amount' => 250.00,
            'override_reason' => 'Bypass attempt without is_rate_override flag in request',
        ];

        $response = $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), $payload);

        $response->assertSessionHasErrors(['rate_override']);
        $sessionLog->refresh();
        $this->assertFalse($sessionLog->is_rate_override);
    }
}
