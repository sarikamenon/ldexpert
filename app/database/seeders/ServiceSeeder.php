<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\ServiceCatalog;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

final class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ServiceCatalog::services() as $serviceData) {
            $payload = Arr::only($serviceData, [
                'name',
                'description',
                'is_direct_service',
                'is_group_service',
                'is_frequency_service',
                'delivery_mode',
                'is_billable',
                'min_duration_minutes',
                'max_duration_minutes',
                'status',
            ]);

            Service::query()->updateOrCreate(
                ['name' => $payload['name']],
                $payload
            );
        }
    }
}
