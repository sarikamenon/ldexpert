<?php

declare(strict_types=1);

namespace App\Domain\Settings\Repositories;

use App\Models\Setting;

interface SettingsRepositoryInterface
{
    /** @return array<string, mixed> */
    public function getGroup(string $group): array;

    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general', bool $isEncrypted = false): Setting;
}
