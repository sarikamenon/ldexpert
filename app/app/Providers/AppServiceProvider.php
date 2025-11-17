<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Repositories\EloquentSchoolRepository;
use App\Infrastructure\Repositories\EloquentTherapistRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Models\School;
use App\Models\TherapistProfile;
use App\Policies\SchoolPolicy;
use App\Policies\TherapistProfilePolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(SchoolRepositoryInterface::class, EloquentSchoolRepository::class);
        $this->app->bind(TherapistRepositoryInterface::class, EloquentTherapistRepository::class);
    }

    public function boot(Router $router): void
    {
        Blade::anonymousComponentNamespace('components.ui', 'ui');
        Blade::anonymousComponentNamespace('components.dashboard', 'dashboard');
        $router->aliasMiddleware('role', RoleMiddleware::class);

        Gate::policy(School::class, SchoolPolicy::class);
        Gate::policy(TherapistProfile::class, TherapistProfilePolicy::class);
    }
}
