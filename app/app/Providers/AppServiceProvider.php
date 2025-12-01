<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\ActivityLog\Repositories\ActivityLogRepositoryInterface;
use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Repositories\EloquentActivityLogRepository;
use App\Infrastructure\Repositories\EloquentSchoolContractRepository;
use App\Infrastructure\Repositories\EloquentSchoolRepository;
use App\Infrastructure\Repositories\EloquentServiceRepository;
use App\Infrastructure\Repositories\EloquentSSARepository;
use App\Infrastructure\Repositories\EloquentTherapistContractRepository;
use App\Infrastructure\Repositories\EloquentStudentRepository;
use App\Infrastructure\Repositories\EloquentScheduleRepository;
use App\Infrastructure\Repositories\EloquentTherapistRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Models\School;
use App\Models\Schedule;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Policies\SchoolPolicy;
use App\Policies\SchoolContractPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\ServicePolicy;
use App\Policies\SSAPolicy;
use App\Policies\StudentProfilePolicy;
use App\Policies\TherapistContractPolicy;
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
        $this->app->bind(StudentRepositoryInterface::class, EloquentStudentRepository::class);
        $this->app->bind(SchoolContractRepositoryInterface::class, EloquentSchoolContractRepository::class);
        $this->app->bind(TherapistContractRepositoryInterface::class, EloquentTherapistContractRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, EloquentServiceRepository::class);
        $this->app->bind(SSARepositoryInterface::class, EloquentSSARepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, EloquentActivityLogRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, EloquentScheduleRepository::class);
    }

    public function boot(Router $router): void
    {
        Blade::anonymousComponentNamespace('components.ui', 'ui');
        Blade::anonymousComponentNamespace('components.dashboard', 'dashboard');
        $router->aliasMiddleware('role', RoleMiddleware::class);

        Gate::policy(School::class, SchoolPolicy::class);
        Gate::policy(TherapistProfile::class, TherapistProfilePolicy::class);
        Gate::policy(StudentProfile::class, StudentProfilePolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(SchoolContract::class, SchoolContractPolicy::class);
        Gate::policy(TherapistContract::class, TherapistContractPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(ServiceSupportAgreement::class, SSAPolicy::class);
    }
}
