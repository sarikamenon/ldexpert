<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SessionLogCommentType;
use App\Models\SessionLog;
use App\Models\SessionLogComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionLogComment>
 */
class SessionLogCommentFactory extends Factory
{
    protected $model = SessionLogComment::class;

    public function definition(): array
    {
        return [
            'session_log_id' => SessionLog::factory(),
            'author_id' => User::factory()->admin(),
            'comment' => $this->faker->sentence(10),
            'type' => SessionLogCommentType::SENT_BACK,
        ];
    }

    public function sentBack(): self
    {
        return $this->state([
            'type' => SessionLogCommentType::SENT_BACK,
        ]);
    }

    public function therapistReply(): self
    {
        return $this->state([
            'type' => SessionLogCommentType::THERAPIST_REPLY,
        ]);
    }
}
