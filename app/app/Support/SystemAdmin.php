<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class SystemAdmin
{
    private const CACHE_KEY = 'system_admin_id';

    private const CACHE_TTL_SECONDS = 3600;

    public static function id(): ?int
    {
        /** @var int|null */
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, static function (): ?int {
            return User::query()
                ->where('role', Role::ADMIN->value)
                ->orderBy('id')
                ->value('id');
        });
    }
}
