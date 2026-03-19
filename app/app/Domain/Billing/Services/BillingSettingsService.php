<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\DTOs\BillingSettingsDTO;
use App\Models\BillingSetting;

final class BillingSettingsService
{
    public function getSettings(): BillingSetting
    {
        return BillingSetting::getSettings();
    }

    public function updateSettings(BillingSettingsDTO $dto): BillingSetting
    {
        $settings = BillingSetting::getSettings();

        $settings->update([
            ...$dto->toArray(),
            'updated_at' => now(),
        ]);

        $settings->refresh();

        return $settings;
    }
}
