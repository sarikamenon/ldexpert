<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubSsa;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleSubSsa>
 */
class ScheduleSubSsaFactory extends Factory
{
    protected $model = ScheduleSubSsa::class;

    public function definition(): array
    {
        return [
            'schedule_sub_request_id' => ScheduleSubRequest::factory(),
            'schedule_id' => Schedule::factory(),
            'ssa_id' => ServiceSupportAgreement::factory(),
            'sub_therapist_id' => User::factory(),
            'student_id' => User::factory(),
            'service_id' => Service::factory(),
            'school_id' => School::factory(),
            'session_date' => $this->faker->date(),
        ];
    }
}
