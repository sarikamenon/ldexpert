<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\SessionLog;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentDocument>
 */
class StudentDocumentFactory extends Factory
{
    protected $model = StudentDocument::class;

    public function definition(): array
    {
        return [
            'documentable_type' => User::class,
            'documentable_id' => User::factory()->student(),
            'uploaded_by_id' => User::factory()->admin(),
            'document_type' => $this->faker->randomElement(DocumentType::cases()),
            'file_name' => $this->faker->word().'.pdf',
            'file_path' => 'student-documents/2026/01/'.$this->faker->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    public function forStudent(User $student): static
    {
        return $this->state(fn (array $attributes) => [
            'documentable_type' => User::class,
            'documentable_id' => $student->id,
        ]);
    }

    public function forSessionLog(SessionLog $sessionLog): static
    {
        return $this->state(fn (array $attributes) => [
            'documentable_type' => SessionLog::class,
            'documentable_id' => $sessionLog->id,
        ]);
    }
}
