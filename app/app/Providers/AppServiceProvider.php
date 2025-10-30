<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Repositories\EloquentUserRepository;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    public function boot(Router $router): void
    {
        Blade::anonymousComponentNamespace('components.ui', 'ui');
        $router->aliasMiddleware('role', RoleMiddleware::class);
    }
}
