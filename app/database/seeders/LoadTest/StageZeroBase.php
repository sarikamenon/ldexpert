<?php

declare(strict_types=1);

namespace Database\Seeders\LoadTest;

use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;

/**
 * Ensures the base entity graph exists (schools, therapists, students, SSAs,
 * contracts) by running the regular demo seeders when the database is empty.
 * The volume stages hang 10 years of history off these entities.
 */
final class StageZeroBase implements StageInterface
{
    public function run(OutputStyle $output, string $scale): bool
    {
        $activeSsas = ServiceSupportAgreement::query()
            ->where('status', SSAStatus::ACTIVE->value)
            ->whereNotNull('assigned_therapist_id')
            ->count();

        if ($activeSsas > 0) {
            $output->writeln("Base data present ({$activeSsas} active assigned SSAs) — skipping.");

            return true;
        }

        $output->writeln('Empty database — running db:seed for the base entity graph…');
        try {
            $exitCode = Artisan::call('db:seed', ['--force' => true]);
        } catch (\Throwable $e) {
            $output->writeln('<error>db:seed threw: '.$e->getMessage().'</error>');
            $output->writeln('  at '.$e->getFile().':'.$e->getLine());

            return false;
        }
        if ($exitCode !== 0) {
            $output->writeln('<error>db:seed failed:</error>');
            $output->writeln(Artisan::output());

            return false;
        }

        $activeSsas = ServiceSupportAgreement::query()
            ->where('status', SSAStatus::ACTIVE->value)
            ->whereNotNull('assigned_therapist_id')
            ->count();

        if ($activeSsas === 0) {
            $output->writeln('<error>db:seed produced no active assigned SSAs — cannot generate history.</error>');

            return false;
        }

        $output->writeln("Base data seeded ({$activeSsas} active assigned SSAs).");

        return true;
    }
}
