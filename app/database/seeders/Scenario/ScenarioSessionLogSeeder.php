<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Domain\Therapist\Services\SessionLogRateService;
use App\Enums\BillingStatus;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

final class ScenarioSessionLogSeeder extends Seeder
{
    /**
     * Create 20 approved session logs per SSA (from completed schedules in 2026), then update SSA served_minutes.
     * Target: ~75 SSAs × 20 = 1500 session log records.
     */
    public function run(): void
    {
        $rateService = app(SessionLogRateService::class);
        $adminId = User::query()->where('role', Role::ADMIN->value)->value('id') ?? 1;

        $ssas = ServiceSupportAgreement::query()
            ->whereNotNull('assigned_therapist_id')
            ->get();

        $notes = [
            'Session focused on academic support. Student was engaged.',
            'Worked on goals. Student demonstrated good progress.',
            'Direct instruction and practice. Student completed tasks.',
        ];

        foreach ($ssas as $ssa) {
            $schedules = Schedule::query()
                ->where('ssa_id', $ssa->id)
                ->where('status', ScheduleStatus::COMPLETED->value)
                ->whereYear('schedule_date', 2026)
                ->orderBy('schedule_date')
                ->limit(20)
                ->with(['therapist', 'student', 'service'])
                ->get();

            $servedTotal = 0;
            $created = 0;

            foreach ($schedules as $schedule) {
                if (! $schedule->school_id) {
                    continue;
                }

                $scheduleDate = Carbon::parse($schedule->schedule_date);
                $startDt = $scheduleDate->copy()->setTime(
                    (int) $schedule->start_time->format('G'),
                    (int) $schedule->start_time->format('i'),
                    0
                );
                $endDt = $scheduleDate->copy()->setTime(
                    (int) $schedule->end_time->format('G'),
                    (int) $schedule->end_time->format('i'),
                    0
                );
                $durationMinutes = (int) round($startDt->diffInMinutes($endDt) / 5) * 5;
                $thoMinutes = $ssa->minutes_per_session ?? $durationMinutes;

                try {
                    $billing = $rateService->calculateDualBilling(
                        $schedule->therapist_id,
                        $schedule->school_id,
                        $schedule->service_id,
                        $scheduleDate->format('Y-m-d'),
                        $durationMinutes,
                        SessionOutcome::SERVICES_ADMINISTERED
                    );
                } catch (ValidationException $e) {
                    continue;
                }

                if (! $billing['therapist']['contract_id'] || ! $billing['school']['contract_id']) {
                    continue;
                }
                if ($billing['therapist']['rate_type'] === null || $billing['therapist']['rate_amount'] === null) {
                    continue;
                }
                if ($billing['school']['rate_type'] === null || $billing['school']['rate_amount'] === null) {
                    continue;
                }

                $submittedAt = $scheduleDate->copy()->addHours(2);
                $approvedAt = $submittedAt->copy()->addHours(24);

                SessionLog::query()->create([
                    'therapist_id' => $schedule->therapist_id,
                    'student_id' => $schedule->student_id,
                    'ssa_id' => $schedule->ssa_id,
                    'schedule_id' => $schedule->id,
                    'service_id' => $schedule->service_id,
                    'school_id' => $schedule->school_id,
                    'session_date' => $scheduleDate->format('Y-m-d'),
                    'start_time' => $startDt->format('Y-m-d H:i:s'),
                    'end_time' => $endDt->format('Y-m-d H:i:s'),
                    'duration_minutes' => $durationMinutes,
                    'tho_minutes' => $thoMinutes,
                    'notes' => $notes[$created % count($notes)],
                    'delivery_mode' => 'in_person',
                    'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
                    'is_group' => false,
                    'is_billable_therapist' => true,
                    'therapist_contract_id' => $billing['therapist']['contract_id'],
                    'therapist_rate_type' => $billing['therapist']['rate_type']->value,
                    'therapist_rate_amount' => $billing['therapist']['rate_amount'],
                    'therapist_billable_amount' => $billing['therapist']['billable_amount'],
                    'therapist_bill_id' => null,
                    'is_billable_school' => true,
                    'school_contract_id' => $billing['school']['contract_id'],
                    'school_rate_type' => $billing['school']['rate_type']->value,
                    'school_rate_amount' => $billing['school']['rate_amount'],
                    'school_invoice_amount' => $billing['school']['invoice_amount'],
                    'invoice_id' => null,
                    'is_rate_override' => false,
                    'override_reason' => null,
                    'status' => SessionLogStatus::APPROVED->value,
                    'submitted_at' => $submittedAt->format('Y-m-d H:i:s'),
                    'submitted_by_id' => $schedule->therapist_id,
                    'approved_at' => $approvedAt->format('Y-m-d H:i:s'),
                    'approved_by_id' => $adminId,
                    'cancellation_reason' => null,
                ]);

                $schedule->update(['billing_status' => BillingStatus::BILLED]);

                $servedTotal += $durationMinutes;
                $created++;
            }

            if ($servedTotal > 0) {
                $ssa->update(['served_minutes' => $servedTotal]);
            }
        }
    }
}
