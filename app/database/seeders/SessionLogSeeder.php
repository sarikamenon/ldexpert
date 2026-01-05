<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Therapist\Services\SessionLogRateService;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class SessionLogSeeder extends Seeder
{
    public function run(): void
    {
        // Get all completed schedules with their relationships
        $schedules = Schedule::where('status', ScheduleStatus::COMPLETED)
            ->with(['ssa', 'therapist', 'student', 'service', 'school'])
            ->get();

        if ($schedules->isEmpty()) {
            $this->command->warn('No completed schedules found. Skipping session log seeding.');

            return;
        }

        // Get an admin user for approved_by_id
        $adminUser = User::where('role', Role::ADMIN)->first();

        // Resolve SessionLogRateService for billing calculation
        $rateService = app(SessionLogRateService::class);

        // Create session logs for a subset of schedules (70% of completed schedules)
        $schedulesToSeed = $schedules->random((int) ceil($schedules->count() * 0.7));

        $totalLogs = 0;
        $skippedLogs = 0;

        foreach ($schedulesToSeed as $schedule) {
            // Skip if schedule doesn't have SSA or required relationships
            if (! $schedule->ssa || ! $schedule->therapist || ! $schedule->student || ! $schedule->school_id) {
                $skippedLogs++;
                continue;
            }

            // Determine status based on distribution (30% draft, 40% submitted, 30% approved)
            $rand = rand(1, 100);
            $status = SessionLogStatus::DRAFT;
            if ($rand <= 30) {
                $status = SessionLogStatus::DRAFT;
            } elseif ($rand <= 70) {
                $status = SessionLogStatus::SUBMITTED;
            } else {
                $status = SessionLogStatus::APPROVED;
            }

            // Calculate duration from schedule times
            $scheduleDate = Carbon::parse($schedule->schedule_date);
            $startDateTime = $scheduleDate->copy()->setTime(
                $schedule->start_time->hour,
                $schedule->start_time->minute,
                0
            );
            $endDateTime = $scheduleDate->copy()->setTime(
                $schedule->end_time->hour,
                $schedule->end_time->minute,
                0
            );
            $durationMinutes = (int) round($startDateTime->diffInMinutes($endDateTime) / 5) * 5; // Round to nearest 5 minutes

            // Get THO minutes from SSA
            $thoMinutes = $schedule->ssa->minutes_per_session ?? $durationMinutes;

            // Determine outcome (mostly service_delivered, occasionally others)
            $outcome = SessionOutcome::SERVICE_DELIVERED;
            $outcomeRand = rand(1, 100);
            if ($outcomeRand <= 5) {
                $outcome = SessionOutcome::NO_SHOW_STUDENT;
            } elseif ($outcomeRand <= 8) {
                $outcome = SessionOutcome::TECHNICAL_ISSUE;
            }

            // Calculate billing amounts
            $billing = $rateService->calculateDualBilling(
                $schedule->therapist_id,
                $schedule->school_id,
                $schedule->service_id,
                $scheduleDate->format('Y-m-d'),
                $durationMinutes
            );

            // Skip if billing data is incomplete (no contracts or service rates)
            if (! $billing['therapist']['contract_id'] || ! $billing['school']['contract_id']) {
                $skippedLogs++;
                continue;
            }

            if (! $billing['therapist']['rate_type'] || $billing['therapist']['rate_amount'] === null) {
                $skippedLogs++;
                continue;
            }

            if (! $billing['school']['rate_type'] || $billing['school']['rate_amount'] === null) {
                $skippedLogs++;
                continue;
            }

            // Apply outcome-based billing flags
            $isBillableTherapist = $outcome->isBillableForTherapist();
            $isBillableSchool = $outcome->isBillableForSchool();

            // Set status-specific fields
            $submittedAt = null;
            $submittedById = null;
            $approvedAt = null;
            $approvedById = null;

            if ($status === SessionLogStatus::SUBMITTED || $status === SessionLogStatus::APPROVED) {
                // Submitted logs have submitted_at and submitted_by_id
                $submittedAt = $scheduleDate->copy()->addHours(rand(1, 48)); // Submitted 1-48 hours after session
                $submittedById = $schedule->therapist_id;
            }

            if ($status === SessionLogStatus::APPROVED) {
                // Approved logs have approved_at and approved_by_id
                $submittedAt = $scheduleDate->copy()->addHours(rand(1, 48)); // Submitted 1-48 hours after session
                $submittedById = $schedule->therapist_id;
                $approvedAt = $submittedAt->copy()->addHours(rand(1, 72)); // Approved 1-72 hours after submission
                $approvedById = $adminUser?->id;
            }

            SessionLog::create([
                'therapist_id' => $schedule->therapist_id,
                'student_id' => $schedule->student_id,
                'ssa_id' => $schedule->ssa_id,
                'schedule_id' => $schedule->id,
                'service_id' => $schedule->service_id,
                'school_id' => $schedule->school_id,
                'session_date' => $scheduleDate->format('Y-m-d'),
                'start_time' => $startDateTime->format('Y-m-d H:i:s'),
                'end_time' => $endDateTime->format('Y-m-d H:i:s'),
                'duration_minutes' => $durationMinutes,
                'tho_minutes' => $thoMinutes,
                'notes' => $this->generateNotes(),
                'delivery_mode' => 'virtual',
                'outcome' => $outcome->value,
                'is_group' => false,
                'is_billable_therapist' => $isBillableTherapist,
                'therapist_contract_id' => $billing['therapist']['contract_id'],
                'therapist_rate_type' => $billing['therapist']['rate_type']?->value,
                'therapist_rate_amount' => $billing['therapist']['rate_amount'],
                'therapist_billable_amount' => $billing['therapist']['billable_amount'],
                'therapist_bill_id' => null,
                'is_billable_school' => $isBillableSchool,
                'school_contract_id' => $billing['school']['contract_id'],
                'school_rate_type' => $billing['school']['rate_type']?->value,
                'school_rate_amount' => $billing['school']['rate_amount'],
                'school_invoice_amount' => $billing['school']['invoice_amount'],
                'invoice_id' => null,
                'is_rate_override' => false,
                'override_reason' => null,
                'status' => $status->value,
                'submitted_at' => $submittedAt?->format('Y-m-d H:i:s'),
                'submitted_by_id' => $submittedById,
                'approved_at' => $approvedAt?->format('Y-m-d H:i:s'),
                'approved_by_id' => $approvedById,
                'cancellation_reason' => null,
            ]);

            $totalLogs++;
        }

        $message = "Session log seeder completed. Created {$totalLogs} session logs.";
        if ($skippedLogs > 0) {
            $message .= " Skipped {$skippedLogs} schedules due to missing contracts or service rates.";
        }
        $this->command->info($message);
    }

    private function generateNotes(): string
    {
        $noteTemplates = [
            'Session focused on academic support and skill-building activities. Student was engaged and participated actively.',
            'Worked on social-emotional learning goals. Student demonstrated good progress and was receptive to feedback.',
            'Provided direct instruction and practice opportunities. Student completed assigned tasks with minimal assistance.',
            'Session included collaborative activities and peer interactions. Student showed improved communication skills.',
            'Focused on individualized instruction tailored to student needs. Good rapport maintained throughout the session.',
            'Student worked on targeted intervention goals. Progress noted in key skill areas.',
            'Session covered planned curriculum objectives. Student remained on task and followed directions well.',
            'Engaged in therapeutic activities designed to support student development. Positive outcomes observed.',
        ];

        return $noteTemplates[array_rand($noteTemplates)];
    }
}
