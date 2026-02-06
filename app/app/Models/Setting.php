<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting.{$key}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            $value = $setting->is_encrypted ? Crypt::decryptString($setting->value) : $setting->value;

            return match ($setting->type) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $value,
                'json' => json_decode($value, true),
                default => $value,
            };
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general', bool $isEncrypted = false): self
    {
        $valueToStore = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        if ($isEncrypted) {
            $valueToStore = Crypt::encryptString($valueToStore);
        }

        $setting = self::updateOrCreate(
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

    public static function getGroup(string $group): array
    {
        $settings = self::where('group', $group)->get();

        return $settings->mapWithKeys(function ($setting) {
            $value = $setting->is_encrypted ? Crypt::decryptString($setting->value) : $setting->value;

            $value = match ($setting->type) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $value,
                'json' => json_decode($value, true),
                default => $value,
            };

            return [$setting->key => $value];
        })->toArray();
    }
}
