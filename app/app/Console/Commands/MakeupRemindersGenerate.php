<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Schedule\Makeup\Services\ScheduleMakeupReminderGenerator;
use App\DTOs\Schedule\Makeup\GenerateMakeupRemindersDTO;
use Illuminate\Console\Command;

final class MakeupRemindersGenerate extends Command
{
    protected $signature = 'makeup-reminders:generate';

    protected $description = 'Scan upcoming school closure events and create pending make-up reminder rows for affected scheduled sessions.';

    public function handle(ScheduleMakeupReminderGenerator $generator): int
    {
        $result = $generator->generate(GenerateMakeupRemindersDTO::fromConfig());

        $this->info(sprintf(
            'Scanned %d closure event(s); created %d pending row(s); %d error(s).',
            $result['events_scanned'],
            $result['rows_created'],
            $result['errors'],
        ));

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
