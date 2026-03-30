<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QGlobRequestStatus;
use App\Models\QGlobRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QGlobRequest>
 */
final class QGlobRequestFactory extends Factory
{
    protected $model = QGlobRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->addDays(1)->format('Y-m-d');

        return [
            'requested_by_id' => User::factory()->therapist()->create()->id,
            'student_id' => User::factory()->student()->create()->id,
            'requested_date' => $date,
            'requested_time' => '14:00:00',
            'note' => fake()->optional()->sentence(),
            'status' => QGlobRequestStatus::PENDING,
            'admin_response' => null,
            'responded_by_id' => null,
            'responded_at' => null,
        ];
    }
}
