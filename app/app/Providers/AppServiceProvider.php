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
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Events\ScheduleCreated;
use App\Events\ScheduleUpdated;
use App\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Repositories\EloquentActivityLogRepository;
use App\Infrastructure\Repositories\EloquentScheduleRepository;
use App\Infrastructure\Repositories\EloquentSchoolContractRepository;
use App\Infrastructure\Repositories\EloquentSchoolRepository;
use App\Infrastructure\Repositories\EloquentServiceRepository;
use App\Infrastructure\Repositories\EloquentSessionLogRepository;
use App\Infrastructure\Repositories\EloquentSSARepository;
use App\Infrastructure\Repositories\EloquentStudentRepository;
use App\Infrastructure\Repositories\EloquentTherapistContractRepository;
use App\Infrastructure\Repositories\EloquentTherapistRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Listeners\SendScheduleNotification;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Policies\SchedulePolicy;
use App\Policies\SchoolContractPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SessionLogPolicy;
use App\Policies\SSAPolicy;
use App\Policies\StudentProfilePolicy;
use App\Policies\TherapistContractPolicy;
use App\Policies\TherapistProfilePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(SessionLogRepositoryInterface::class, EloquentSessionLogRepository::class);

        $this->app->singleton(UserTimezoneService::class, static function (): UserTimezoneService {
            return new UserTimezoneService(config('app.timezone', 'UTC'));
        });
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
        Gate::policy(SessionLog::class, SessionLogPolicy::class);
        Gate::policy(SchoolContract::class, SchoolContractPolicy::class);
        Gate::policy(TherapistContract::class, TherapistContractPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(ServiceSupportAgreement::class, SSAPolicy::class);

        Event::listen(ScheduleCreated::class, SendScheduleNotification::class);
        Event::listen(ScheduleUpdated::class, SendScheduleNotification::class);

        Collection::macro('withUserTimezone', function (?User $user = null, array $fields = ['created_at', 'updated_at']) {
            /** @var \Illuminate\Support\Collection $this */
            /** @var UserTimezoneService $tzService */
            $tzService = app(UserTimezoneService::class);
            $user = $user ?? Auth::user();

            return $this->map(function ($item) use ($tzService, $user, $fields) {
                if (! $item instanceof Model) {
                    return $item;
                }

                foreach ($fields as $field) {
                    $value = $item->getAttribute($field);

                    if ($value instanceof \Carbon\CarbonInterface) {
                        $local = $tzService->toUserTimezone($value, $user);
                        $item->setAttribute($field.'_local', $local);
                    }
                }

                return $item;
            });
        });
    }
}
