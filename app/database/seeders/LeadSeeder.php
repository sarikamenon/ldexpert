<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Seed demo leads across various pipeline stages with notes.
     */
    public function run(): void
    {
        $adminIds = User::query()
            ->where('role', 'admin')
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            $this->command->warn('LeadSeeder: no admin users found, skipping.');

            return;
        }

        $schoolIds = School::query()->pluck('id');

        $createdByFn = fn (): int => $adminIds->random();
        $schoolFn = fn (): ?int => $schoolIds->isNotEmpty() && fake()->boolean(70)
            ? $schoolIds->random()
            : null;

        // Active pipeline leads — Inquiry (10)
        Lead::factory()
            ->count(10)
            ->state(fn (): array => [
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])
            ->create();

        // Contacted (8)
        Lead::factory()
            ->count(8)
            ->contacted()
            ->state(fn (): array => [
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])
            ->create();

        // Follow Up with upcoming dates (6)
        Lead::factory()
            ->count(6)
            ->followUp()
            ->state(fn (): array => [
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])
            ->create();

        // Overdue follow-ups (4)
        Lead::factory()
            ->count(4)
            ->overdueFollowUp()
            ->state(fn (): array => [
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])
            ->create();

        // Evaluation (5)
        Lead::factory()
            ->count(5)
            ->evaluation()
            ->state(fn (): array => [
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])
            ->create();

        // Enrolled / converted (3)
        Lead::factory()
            ->count(3)
            ->enrolled()
            ->state(fn (): array => [
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])
            ->create();

        // Declined (3)
        Lead::factory()
            ->count(3)
            ->declined()
            ->state(fn (): array => [
                'created_by' => $createdByFn(),
                'school_id' => $schoolFn(),
            ])
            ->create();

        // Other negative terminals (6 total, 2 each)
        foreach ([LeadStatus::NOT_ELIGIBLE, LeadStatus::NO_RESPONSE, LeadStatus::WITHDRAWN] as $status) {
            Lead::factory()
                ->count(2)
                ->state(fn (): array => [
                    'status' => $status->value,
                    'status_reason' => fake()->sentence(),
                    'created_by' => $createdByFn(),
                    'school_id' => $schoolFn(),
                ])
                ->create();
        }

        // Add notes to ~60% of leads
        $leads = Lead::query()->inRandomOrder()->limit(30)->get();
        foreach ($leads as $lead) {
            $noteCount = fake()->numberBetween(1, 4);
            LeadNote::factory()
                ->count($noteCount)
                ->state(fn (): array => [
                    'lead_id' => $lead->id,
                    'author_id' => $createdByFn(),
                ])
                ->create();
        }
    }
}
