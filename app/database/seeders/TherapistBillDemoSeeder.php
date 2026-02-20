<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Finance\Services\LedgerService;
use App\Enums\Role;
use App\Enums\TherapistBillStatus;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Database\Seeder;

class TherapistBillDemoSeeder extends Seeder
{
    private const DEMO_NOTE_MARKER = '[demo]';

    /**
     * Seed demo therapist bills for a subset of therapists.
     */
    public function run(): void
    {
        // Run demo therapist bills in all non-production environments.
        if (app()->environment('production')) {
            return;
        }

        // If we've already created demo bills, don't create them again.
        if (TherapistBill::where('notes', 'like', '%'.self::DEMO_NOTE_MARKER.'%')->exists()) {
            return;
        }

        /** @var User|null $admin */
        $admin = User::query()->orderBy('id')->first();

        /** @var LedgerService $ledgerService */
        $ledgerService = app(LedgerService::class);

        // Use existing therapists (users seeded by TherapistSeeder).
        $therapists = User::query()
            ->where('role', Role::THERAPIST->value)
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($therapists->isEmpty()) {
            // Fallback: create a few generic therapist users if none exist.
            $therapists = User::factory()->count(3)->create();
        }

        foreach ($therapists as $index => $therapist) {
            $this->createBillsForTherapist($therapist, $admin, $ledgerService, $index + 1);
        }
    }

    private function createBillsForTherapist(
        User $therapist,
        ?User $admin,
        LedgerService $ledgerService,
        int $therapistIndex,
    ): void {
        // 2 draft, 2 sent, 2 paid per therapist (medium volume overall)
        $counts = [
            TherapistBillStatus::DRAFT->value => 2,
            TherapistBillStatus::SENT->value => 2,
            TherapistBillStatus::PAID->value => 2,
        ];

        foreach ($counts as $statusValue => $count) {
            for ($i = 1; $i <= $count; $i++) {
                /** @var TherapistBill $bill */
                $bill = TherapistBill::factory()
                    ->for($therapist, 'therapist')
                    ->state([
                        // Use factory-generated bill_number to avoid unique constraint collisions.
                        'status' => $statusValue,
                        'sent_by_id' => $statusValue !== TherapistBillStatus::DRAFT->value ? $admin?->id : null,
                        'sent_at' => $statusValue !== TherapistBillStatus::DRAFT->value ? now() : null,
                        'notes' => trim('Finance module demo bill '.self::DEMO_NOTE_MARKER),
                    ])
                    ->create();

                // Ensure ledger reflects bill generation for this therapist.
                $ledgerService->createBillGeneratedEntry($bill);
            }
        }
    }
}
