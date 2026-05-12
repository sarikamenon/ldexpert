<?php

namespace Database\Factories;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'therapist_id' => User::factory(),
            'student_id' => User::factory(),
            'ssa_id' => ServiceSupportAgreement::factory(),
            'service_id' => Service::factory(),
            'school_id' => School::factory(),
            'parent_schedule_id' => null,
            'schedule_date' => $this->faker->date(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => RecurrenceType::NONE,
            'recurrence_end_date' => null,
            'is_group' => false,
            'recurring_batch_number' => null,
            'group_batch_number' => null,
            'status' => ScheduleStatus::SCHEDULED,
            'billing_status' => BillingStatus::PENDING,
            'is_billable' => true,
            'notes' => $this->faker->sentence(),
            'location_details' => null,
        ];
    }
}
