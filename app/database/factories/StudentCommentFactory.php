<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StudentComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentComment>
 */
class StudentCommentFactory extends Factory
{
    protected $model = StudentComment::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'author_id' => User::factory()->admin(),
            'comment' => $this->faker->paragraph(2),
        ];
    }
}
