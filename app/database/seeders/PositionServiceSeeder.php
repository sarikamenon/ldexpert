<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Service;
use Illuminate\Database\Seeder;

final class PositionServiceSeeder extends Seeder
{
    /**
     * Common services attached to all positions (IEP Meetings, Evaluations, Scheduling, etc.).
     */
    private const COMMON_SERVICE_NAMES = [
        'IEP Meetings',
        'Evaluations (Speech, Occupational)',
        'Evaluations - Academic/Cognitive',
        'Scheduling',
        'IEP Paperwork',
        'Progress Reports',
        'IEP Progress Monitoring & Documentation',
    ];

    /**
     * Position name => dedicated service names (position-specific).
     *
     * @var array<string, list<string>>
     */
    private const DEDICATED_SERVICES = [
        'SLP' => [
            'Speech Therapy',
            'Individual Speech-Language Therapy (K-5)',
            'Speech-Language Therapy Small Group',
        ],
        'OT' => [
            'Occupational Therapy',
            'Occupational Therapy - Sensory Integration',
            'Occupational Therapy - Handwriting Intensive',
        ],
        'PT' => [
            'Physical Therapy - Gross Motor Coaching',
            'Physical Therapy - Adaptive Equipment Training',
        ],
        'LCSW' => [
            'School-Based Counseling Session',
            'Behavioral Health Check-In',
        ],
        'SW' => [
            'School-Based Counseling Session',
            'Behavioral Health Check-In',
        ],
        'BCBA' => [
            'Applied Behavior Analysis Consultation',
            'Family Coaching & Care Coordination Call',
        ],
        'RBT' => [
            'Behavior Technician Implementation Support',
        ],
    ];

    public function run(): void
    {
        $servicesByName = Service::query()->pluck('id', 'name');

        foreach (Position::query()->active()->get() as $position) {
            $dedicatedNames = self::DEDICATED_SERVICES[$position->name] ?? [];
            $allNames = array_unique(array_merge($dedicatedNames, self::COMMON_SERVICE_NAMES));
            $serviceIds = [];

            foreach ($allNames as $name) {
                if (isset($servicesByName[$name])) {
                    $serviceIds[] = $servicesByName[$name];
                }
            }

            $position->services()->sync(array_values($serviceIds));
        }
    }
}
