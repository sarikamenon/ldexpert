<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

final class ScenarioLeadSeeder extends Seeder
{
    /**
     * Create 45 leads across all pipeline stages with notes.
     */
    public function run(): void
    {
        $adminIds = User::query()
            ->where('role', Role::ADMIN->value)
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            $this->command->warn('ScenarioLeadSeeder: no admin users found, skipping.');

            return;
        }

        $schoolIds = School::query()->pluck('id');

        $createdByFn = fn (): int => $adminIds->random();
        $schoolFn = fn (): ?int => $schoolIds->isNotEmpty()
            ? $schoolIds->random()
            : null;

        // Active pipeline: Inquiry (10), Contacted (8), Follow Up (6), Evaluation (5)
        Lead::factory()->count(10)->state(fn (): array => [
            'created_by' => $createdByFn(),
            'school_id' => $schoolFn(),
        ])->create();

        Lead::factory()->count(8)->contacted()->state(fn (): array => [
            'created_by' => $createdByFn(),
            'school_id' => $schoolFn(),
        ])->create();

        Lead::factory()->count(6)->followUp()->state(fn (): array => [
            'created_by' => $createdByFn(),
            'school_id' => $schoolFn(),
        ])->create();

        // Overdue follow-ups (4)
        Lead::factory()->count(4)->overdueFollowUp()->state(fn (): array => [
            'created_by' => $createdByFn(),
            'school_id' => $schoolFn(),
        ])->create();

        Lead::factory()->count(5)->evaluation()->state(fn (): array => [
            'created_by' => $createdByFn(),
            'school_id' => $schoolFn(),
        ])->create();

        // Terminal: Enrolled (3), Declined (3), others (6)
        Lead::factory()->count(3)->enrolled()->state(fn (): array => [
            'created_by' => $createdByFn(),
            'school_id' => $schoolFn(),
        ])->create();

        Lead::factory()->count(3)->declined()->state(fn (): array => [
            'created_by' => $createdByFn(),
            'school_id' => $schoolFn(),
        ])->create();

        foreach ([LeadStatus::NOT_ELIGIBLE, LeadStatus::NO_RESPONSE, LeadStatus::WITHDRAWN] as $status) {
            Lead::factory()->count(2)->state(fn (): array => [
                'status' => $status->value,
                'status_reason' => fake()->sentence(),
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])->create();
        }

        // Add 1-4 notes to ~60% of leads
        $leads = Lead::query()->inRandomOrder()->limit(27)->get();
        foreach ($leads as $lead) {
            LeadNote::factory()
                ->count(fake()->numberBetween(1, 4))
                ->state(fn (): array => [
                    'lead_id' => $lead->id,
                    'author_id' => $createdByFn(),
                ])
                ->create();
        }
    }
}
