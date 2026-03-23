<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class ScenarioScheduleSeeder extends Seeder
{
    /**
     * Create 30 schedules per SSA, all in 2026. 20 "past" (completed), 10 "future" (scheduled).
     * Target: ~75 SSAs × 30 = 2250 schedule entries.
     */
    public function run(): void
    {
        $ssas = ServiceSupportAgreement::query()
            ->whereIn('status', [SSAStatus::ACTIVE, SSAStatus::COMPLETED])
            ->whereNotNull('assigned_therapist_id')
            ->with(['student.studentProfile', 'primaryService'])
            ->get();

        $year2026Start = Carbon::create(2026, 1, 1);
        $year2026End = Carbon::create(2026, 6, 30);
        $recurrenceType = RecurrenceType::WEEKLY;

        foreach ($ssas as $ssa) {
            $schoolId = $ssa->student?->studentProfile?->school_id;
            $start = Carbon::parse($ssa->start_date);
            if ($start->year < 2026) {
                $start = $year2026Start->copy();
            }
            if ($start->gt($year2026End)) {
                continue;
            }
            $end = $year2026End->copy();
            if ($ssa->end_date->year === 2026 && $ssa->end_date->lt($end)) {
                $end = Carbon::parse($ssa->end_date);
            }

            $dates = $this->generateThirtyDatesIn2026($start, $end);
            if (count($dates) < 30) {
                continue;
            }

            foreach (array_values($dates) as $i => $scheduleDate) {
                $isPast = $i < 20;
                $status = $isPast ? ScheduleStatus::COMPLETED : ScheduleStatus::SCHEDULED;
                $billingStatus = BillingStatus::PENDING;

                $startTime = Carbon::createFromTime(8 + ($i % 8), ($i % 4) * 15, 0);
                $endTime = $startTime->copy()->addMinutes($ssa->minutes_per_session);

                Schedule::query()->create([
                    'therapist_id' => $ssa->assigned_therapist_id,
                    'student_id' => $ssa->student_id,
                    'ssa_id' => $ssa->id,
                    'service_id' => $ssa->primary_service_id,
                    'school_id' => $schoolId,
                    'schedule_date' => $scheduleDate->format('Y-m-d'),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'recurrence_type' => $recurrenceType->value,
                    'recurrence_end_date' => $ssa->end_date->format('Y-m-d'),
                    'is_group' => false,
                    'status' => $status->value,
                    'billing_status' => $billingStatus->value,
                ]);
            }
        }
    }

    /**
     * Generate up to 30 weekday dates within the given range (2026 school year).
     *
     * @return array<int, Carbon>
     */
    private function generateThirtyDatesIn2026(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $current = $start->copy()->startOfDay();
        $endCopy = $end->copy()->startOfDay();

        while ($current->lte($endCopy) && count($dates) < 30) {
            if ($current->dayOfWeek !== 0 && $current->dayOfWeek !== 6) {
                $dates[] = $current->copy();
            }
            $current->addDay();
        }

        if (count($dates) < 30) {
            $current = $start->copy();
            $seen = array_fill_keys(array_map(fn (Carbon $d) => $d->format('Y-m-d'), $dates), true);
            while (count($dates) < 30) {
                $key = $current->format('Y-m-d');
                if ($current->dayOfWeek !== 0 && $current->dayOfWeek !== 6 && ! isset($seen[$key])) {
                    $seen[$key] = true;
                    $dates[] = $current->copy();
                }
                $current->addDay();
                if ($current->gt($endCopy)) {
                    $current = $start->copy();
                }
            }
        }

        $dates = array_slice(array_values($dates), 0, 30);
        usort($dates, fn (Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp);

        return $dates;
    }
}
