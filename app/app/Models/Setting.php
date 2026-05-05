<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAudits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasAudits;

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

            /** @var string $rawValue */
            $rawValue = $setting->value ?? '';
            $value = $setting->is_encrypted ? Crypt::decryptString($rawValue) : $rawValue;

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
            $valueToStore = Crypt::encryptString((string) $valueToStore);
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

    /** @return array<string, mixed> */
    public static function getGroup(string $group): array
    {
        $settings = self::where('group', $group)->get();

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
}
