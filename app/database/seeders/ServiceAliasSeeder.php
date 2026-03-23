<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SSAImportType;
use App\Models\Service;
use App\Models\ServiceAlias;
use Illuminate\Database\Seeder;

final class ServiceAliasSeeder extends Seeder
{
    /**
     * Known RSM external service names mapped to system service names.
     *
     * @var array<string, string>
     */
    private const RSM_ALIASES = [
        'Speech Therapy Online' => 'Speech Therapy',
        'Occupational Therapy Online' => 'Occupational Therapy',
        'Speech Therapy Init-Evaluation Online' => 'Evaluations (Speech, Occupational)',
        'Occupational Therapy Init-Evaluation Online' => 'Evaluations (Speech, Occupational)',
        'Speech Therapy Re-Evaluation Online' => 'Evaluations (Speech, Occupational)',
        'Occupational Therapy Re-Evaluation Online' => 'Evaluations (Speech, Occupational)',
        'Speech Therapy Meeting Attendance' => 'IEP Meetings',
        'Occupational Therapy Meeting Attendance' => 'IEP Meetings',
        'Augmentative and Alternative Communication Services Consultation Online' => 'Speech Therapy',
    ];

    public function run(): void
    {
        foreach (self::RSM_ALIASES as $externalName => $systemServiceName) {
            $service = Service::where('name', $systemServiceName)->first();

            if (! $service) {
                $this->command->warn("Service '{$systemServiceName}' not found — skipping alias '{$externalName}'.");

                continue;
            }

            ServiceAlias::updateOrCreate(
                [
                    'source' => SSAImportType::RSM->value,
                    'external_name' => $externalName,
                ],
                [
                    'service_id' => $service->id,
                ]
            );
        }

        $this->command->info('Service aliases seeded: '.count(self::RSM_ALIASES).' RSM aliases processed.');
    }
}
