<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Settings\Repositories\SettingsRepositoryInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class EloquentSettingsRepository implements SettingsRepositoryInterface
{
    /** @return array<string, mixed> */
    public function getGroup(string $group): array
    {
        $settings = Setting::where('group', $group)->get();

        return $settings->mapWithKeys(function ($setting) {
            /** @var string $rawValue */
            $rawValue = $setting->value ?? '';
            $value = $setting->is_encrypted ? Crypt::decryptString($rawValue) : $rawValue;

            $value = match ($setting->type) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $value,
                'json' => json_decode($value, true),
                default => $value,
            };

            return [$setting->key => $value];
        })->toArray();
    }

    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general', bool $isEncrypted = false): Setting
    {
        $valueToStore = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        if ($isEncrypted) {
            $valueToStore = Crypt::encryptString((string) $valueToStore);
        }

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $valueToStore,
                'type' => $type,
                'group' => $group,
                'is_encrypted' => $isEncrypted,
            ]
        );

        Cache::forget("setting.{$key}");

        return $setting;
    }
}
