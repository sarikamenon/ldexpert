<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Models\StudentProfile;
use App\Policies\StudentProfilePolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        StudentProfile::class => StudentProfilePolicy::class,
    ];

    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    public function boot(Router $router): void
    {
        Blade::anonymousComponentNamespace('components.ui', 'ui');
        Blade::anonymousComponentNamespace('components.dashboard', 'dashboard');
        $router->aliasMiddleware('role', RoleMiddleware::class);

        Gate::policy(StudentProfile::class, StudentProfilePolicy::class);
    }
}
