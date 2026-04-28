<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SchoolStatus;
use App\Enums\SSAStatus;
use App\Mail\SchoolContractAutoExtendedMail;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SchoolContractAutoExtend extends Command
{
    protected $signature = 'school:auto-extend-contracts-ssas
        {--school= : Target a specific school ID}
        {--dry-run : Preview without saving or sending email}';

    protected $description = 'Auto-extend active contracts and SSAs for private-student schools with is_auto_extend enabled';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $schoolId = $this->option('school');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved and no emails will be sent.');
            $this->newLine();
        }

        $query = School::query()
            ->where('is_private_student', true)
            ->where('is_auto_extend', true)
            ->where('status', SchoolStatus::ACTIVE->value)
            ->with('manager');

        if ($schoolId !== null) {
            $query->where('id', (int) $schoolId);
        }

        $schools = $query->get();

        if ($schools->isEmpty()) {
            $this->info('No eligible schools found.');

            return self::SUCCESS;
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = [];

        foreach ($schools as $school) {
            $results[] = $this->extendSchool($school, $dryRun);
        }

        $this->newLine();
        $this->table(
            ['School', 'Old End Date', 'New End Date', 'SSAs Extended', 'Skipped Reason'],
            array_map(fn (array $r): array => [
                $r['school_name'],
                $r['old_end_date'] ?? '—',
                $r['new_end_date'] ?? '—',
                $r['ssas_extended'] ?? '—',
                $r['skipped_reason'] ?? '',
            ], $results)
        );

        $extended = count(array_filter($results, fn (array $r): bool => ($r['skipped_reason'] ?? null) === null));
        $skipped = count($results) - $extended;
        $this->info("Done. Extended: {$extended}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function extendSchool(School $school, bool $dryRun): array
    {
        $base = [
            'school_id' => $school->id,
            'school_name' => $school->display_name,
        ];

        $localToday = now()->setTimezone($school->timezone)->toDateString();

        $contract = SchoolContract::query()
            ->active()
            ->forSchool($school->id)
            ->orderByDesc('end_date')
            ->first();

        $contractDue = $contract && ! $contract->end_date->greaterThan($localToday);

        $ssasDue = ServiceSupportAgreement::query()
            ->where('status', SSAStatus::ACTIVE)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', $localToday)
            ->whereHas('student.studentProfile', fn ($q) => $q->where('school_id', $school->id)) // @phpstan-ignore argument.type
            ->get();

        if (! $contractDue && $ssasDue->isEmpty()) {
            $reason = $contract === null
                ? 'no active contract and no SSAs due'
                : 'nothing due (contract ends '.$contract->end_date->toDateString().', no SSAs expiring)';

            return array_merge($base, ['skipped_reason' => $reason]);
        }

        $oldEndDate = $contract && $contractDue ? $contract->end_date->copy() : null;
        $newEndDate = $oldEndDate?->copy()->addYear();

        if ($dryRun) {
            return array_merge($base, [
                'old_end_date' => $oldEndDate?->toDateString(),
                'new_end_date' => $newEndDate?->toDateString(),
                'ssas_extended' => $ssasDue->count(),
            ]);
        }

        DB::transaction(function () use ($contract, $contractDue, $ssasDue): void {
            if ($contract && $contractDue) {
                $contract->update(['end_date' => $contract->end_date->addYear()]);
            }

            foreach ($ssasDue as $ssa) {
                if ($ssa->end_date !== null) {
                    $ssa->update(['end_date' => $ssa->end_date->addYear()]);
                }
            }
        });

        if ($contract && $contractDue && $oldEndDate) {
            $manager = $school->manager;
            if ($manager && $manager->email) {
                Mail::to($manager->email)->queue(
                    new SchoolContractAutoExtendedMail(
                        $school,
                        $contract,
                        Carbon::instance($oldEndDate),
                        $ssasDue->count()
                    )
                );
            } else {
                $this->warn("School [{$school->display_name}]: extended but no manager email — notification skipped.");
            }
        }

        return array_merge($base, [
            'old_end_date' => $oldEndDate?->toDateString(),
            'new_end_date' => $contract && $contractDue ? $contract->end_date->toDateString() : null,
            'ssas_extended' => $ssasDue->count(),
        ]);
    }
}
