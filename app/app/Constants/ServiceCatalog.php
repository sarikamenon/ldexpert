<?php

declare(strict_types=1);

namespace App\Constants;

use App\Enums\RateType;
use App\Enums\ServiceFrequency;
use App\Enums\ServiceStatus;
use App\Enums\TherapistPosition;

final class ServiceCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function services(): array
    {
        return [
            [
                'name' => 'Individual Speech-Language Therapy (K-5)',
                'description' => 'Evidence-based articulation and expressive language sessions for elementary students.',
                'direct_service' => true,
                'group_service' => false,
                'frequency' => ServiceFrequency::WEEKLY->value,
                'delivery_mode' => 'in_person',
                'is_billable' => true,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 60,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::SLP->value],
                'rate' => 95.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Speech-Language Therapy Small Group',
                'description' => 'Fluency and pragmatic language groups targeting social communication.',
                'direct_service' => true,
                'group_service' => true,
                'frequency' => ServiceFrequency::WEEKLY->value,
                'delivery_mode' => 'hybrid',
                'is_billable' => true,
                'min_duration_minutes' => 45,
                'max_duration_minutes' => 60,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::SLP->value],
                'rate' => 110.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Occupational Therapy - Sensory Integration',
                'description' => 'Direct OT sessions focused on regulation, postural control, and adaptive responses.',
                'direct_service' => true,
                'group_service' => false,
                'frequency' => ServiceFrequency::WEEKLY->value,
                'delivery_mode' => 'in_person',
                'is_billable' => true,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 60,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::OT->value],
                'rate' => 105.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Occupational Therapy - Handwriting Intensive',
                'description' => 'Targeted OT intervention for fine-motor coordination and written expression.',
                'direct_service' => true,
                'group_service' => false,
                'frequency' => ServiceFrequency::WEEKLY->value,
                'delivery_mode' => 'hybrid',
                'is_billable' => true,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 45,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::OT->value],
                'rate' => 100.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Physical Therapy - Gross Motor Coaching',
                'description' => 'PT sessions addressing balance, strength, and mobility goals in school settings.',
                'direct_service' => true,
                'group_service' => false,
                'frequency' => ServiceFrequency::WEEKLY->value,
                'delivery_mode' => 'in_person',
                'is_billable' => true,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 60,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::PT->value],
                'rate' => 115.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Physical Therapy - Adaptive Equipment Training',
                'description' => 'Consult sessions for positioning plans, wheelchairs, and adaptive equipment trials.',
                'direct_service' => false,
                'group_service' => false,
                'frequency' => ServiceFrequency::MONTHLY->value,
                'delivery_mode' => 'hybrid',
                'is_billable' => true,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 90,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::PT->value],
                'rate' => 120.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'School-Based Counseling Session',
                'description' => 'Tier III counseling aligned to IEP goals for emotional regulation and coping.',
                'direct_service' => true,
                'group_service' => false,
                'frequency' => ServiceFrequency::WEEKLY->value,
                'delivery_mode' => 'hybrid',
                'is_billable' => true,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 45,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::LCSW->value, TherapistPosition::SW->value],
                'rate' => 90.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Behavioral Health Check-In',
                'description' => 'Brief solution-focused counseling check-ins for progress monitoring.',
                'direct_service' => true,
                'group_service' => false,
                'frequency' => ServiceFrequency::WEEKLY->value,
                'delivery_mode' => 'virtual',
                'is_billable' => true,
                'min_duration_minutes' => 20,
                'max_duration_minutes' => 30,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::LCSW->value, TherapistPosition::SW->value],
                'rate' => 70.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Applied Behavior Analysis Consultation',
                'description' => 'BCBA-led classroom observations, data review, and intervention planning.',
                'direct_service' => false,
                'group_service' => false,
                'frequency' => ServiceFrequency::MONTHLY->value,
                'delivery_mode' => 'in_person',
                'is_billable' => true,
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 120,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::BCBA->value],
                'rate' => 140.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Behavior Technician Implementation Support',
                'description' => 'On-campus RBT implementation of BCBA treatment plans with live coaching.',
                'direct_service' => true,
                'group_service' => false,
                'frequency' => ServiceFrequency::DAILY->value,
                'delivery_mode' => 'in_person',
                'is_billable' => true,
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 180,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [TherapistPosition::RBT->value],
                'rate' => 65.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'IEP Progress Monitoring & Documentation',
                'description' => 'Indirect service for data analysis, goal updates, and narrative reporting.',
                'direct_service' => false,
                'group_service' => false,
                'frequency' => ServiceFrequency::MONTHLY->value,
                'delivery_mode' => 'virtual',
                'is_billable' => true,
                'min_duration_minutes' => 45,
                'max_duration_minutes' => 90,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [
                    TherapistPosition::SLP->value,
                    TherapistPosition::OT->value,
                    TherapistPosition::PT->value,
                    TherapistPosition::LCSW->value,
                    TherapistPosition::SW->value,
                    TherapistPosition::BCBA->value,
                ],
                'rate' => 85.00,
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'name' => 'Family Coaching & Care Coordination Call',
                'description' => 'Scheduled coaching with caregivers to reinforce school-based plans.',
                'direct_service' => false,
                'group_service' => false,
                'frequency' => ServiceFrequency::MONTHLY->value,
                'delivery_mode' => 'virtual',
                'is_billable' => true,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 45,
                'status' => ServiceStatus::ACTIVE->value,
                'positions' => [
                    TherapistPosition::BCBA->value,
                    TherapistPosition::LCSW->value,
                    TherapistPosition::SW->value,
                ],
                'rate' => 80.00,
                'rate_type' => RateType::HOURLY->value,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function servicesForPosition(TherapistPosition $position): array
    {
        return array_values(
            array_filter(
                self::services(),
                static fn(array $service): bool => in_array($position->value, $service['positions'], true)
            )
        );
    }
}
