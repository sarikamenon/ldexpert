<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\RateType;
use App\Models\SessionLog;
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
        $this->assertSame(200.00, (float) $sessionLog->therapist_billable_amount);
        $this->assertSame(250.00, (float) $sessionLog->school_invoice_amount);
    }

    public function test_admin_cannot_override_finalized_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->finalized()->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.session-logs.update', $sessionLog), [
                'therapist_rate_type' => RateType::FLAT->value,
                'therapist_rate_amount' => 200.00,
                'therapist_billable_amount' => 200.00,
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
}
