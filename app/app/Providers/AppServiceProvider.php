<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\Domain\Dashboard\Repositories\DashboardRepositoryInterface;
use App\Domain\Finance\Repositories\FinanceSummaryRepositoryInterface;
use App\Domain\Finance\Repositories\InvoicePaymentRepositoryInterface;
use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\Domain\Finance\Repositories\TherapistBillPaymentRepositoryInterface;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Lead\Repositories\LeadRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Position\Repositories\PositionRepositoryInterface;
use App\Domain\School\Repositories\SchoolCalendarEventRepositoryInterface;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\Settings\Repositories\SettingsRepositoryInterface;
use App\Domain\SSA\Repositories\SSAImportRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Storage\Services\StorageServiceInterface;
use App\Domain\Student\Repositories\StudentCommentRepositoryInterface;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\Repositories\StudentImportRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Events\ScheduleCreated;
use App\Events\ScheduleUpdated;
use App\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Repositories\EloquentAnalyticsRepository;
use App\Infrastructure\Repositories\EloquentDashboardRepository;
use App\Infrastructure\Repositories\EloquentFinanceSummaryRepository;
use App\Infrastructure\Repositories\EloquentInvoicePaymentRepository;
use App\Infrastructure\Repositories\EloquentInvoiceRepository;
use App\Infrastructure\Repositories\EloquentLeadRepository;
use App\Infrastructure\Repositories\EloquentLedgerEntryRepository;
use App\Infrastructure\Repositories\EloquentNotificationRepository;
use App\Infrastructure\Repositories\EloquentPositionRepository;
use App\Infrastructure\Repositories\EloquentScheduleRepository;
use App\Infrastructure\Repositories\EloquentSchoolCalendarEventRepository;
use App\Infrastructure\Repositories\EloquentSchoolContractRepository;
use App\Infrastructure\Repositories\EloquentSchoolRepository;
use App\Infrastructure\Repositories\EloquentServiceRepository;
use App\Infrastructure\Repositories\EloquentSessionLogRepository;
use App\Infrastructure\Repositories\EloquentSettingsRepository;
use App\Infrastructure\Repositories\EloquentSSAImportRepository;
use App\Infrastructure\Repositories\EloquentSSARepository;
use App\Infrastructure\Repositories\EloquentStudentCommentRepository;
use App\Infrastructure\Repositories\EloquentStudentDocumentRepository;
use App\Infrastructure\Repositories\EloquentStudentImportRepository;
use App\Infrastructure\Repositories\EloquentStudentRepository;
use App\Infrastructure\Repositories\EloquentTherapistBillPaymentRepository;
use App\Infrastructure\Repositories\EloquentTherapistBillRepository;
use App\Infrastructure\Repositories\EloquentTherapistContractRepository;
use App\Infrastructure\Repositories\EloquentTherapistRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Infrastructure\Services\Storage\LocalStorageService;
use App\Infrastructure\Services\Storage\S3StorageService;
use App\Listeners\SendScheduleNotification;
use App\Models\Lead;
use App\Models\Invoice;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\ServiceAlias;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\StudentComment;
use App\Models\StudentDocument;
use App\Models\StudentProfile;
use App\Models\TherapistBill;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Policies\LeadPolicy;
use App\Policies\PositionPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\ServiceAliasPolicy;
use App\Policies\SchoolCalendarEventPolicy;
use App\Policies\SchoolContractPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SessionLogPolicy;
use App\Policies\SSAPolicy;
use App\Policies\StudentCommentPolicy;
use App\Policies\StudentDocumentPolicy;
use App\Policies\StudentProfilePolicy;
use App\Policies\TherapistBillPolicy;
use App\Policies\TherapistContractPolicy;
use App\Policies\TherapistProfilePolicy;
use Illuminate\Auth\Notifications\ResetPassword;
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
        $this->app->bind(StudentCommentRepositoryInterface::class, EloquentStudentCommentRepository::class);
        $this->app->bind(StudentDocumentRepositoryInterface::class, EloquentStudentDocumentRepository::class);
        $this->app->bind(StudentImportRepositoryInterface::class, EloquentStudentImportRepository::class);
        $this->app->bind(SchoolContractRepositoryInterface::class, EloquentSchoolContractRepository::class);
        $this->app->bind(TherapistContractRepositoryInterface::class, EloquentTherapistContractRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, EloquentServiceRepository::class);
        $this->app->bind(PositionRepositoryInterface::class, EloquentPositionRepository::class);
        $this->app->bind(SSAImportRepositoryInterface::class, EloquentSSAImportRepository::class);
        $this->app->bind(SSARepositoryInterface::class, EloquentSSARepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, EloquentScheduleRepository::class);
        $this->app->bind(SessionLogRepositoryInterface::class, EloquentSessionLogRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);
        $this->app->bind(TherapistBillRepositoryInterface::class, EloquentTherapistBillRepository::class);
        $this->app->bind(AnalyticsRepositoryInterface::class, EloquentAnalyticsRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, EloquentDashboardRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, EloquentSettingsRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(SchoolCalendarEventRepositoryInterface::class, EloquentSchoolCalendarEventRepository::class);
        $this->app->bind(LedgerEntryRepositoryInterface::class, EloquentLedgerEntryRepository::class);
        $this->app->bind(InvoicePaymentRepositoryInterface::class, EloquentInvoicePaymentRepository::class);
        $this->app->bind(TherapistBillPaymentRepositoryInterface::class, EloquentTherapistBillPaymentRepository::class);
        $this->app->bind(LeadRepositoryInterface::class, EloquentLeadRepository::class);
        $this->app->bind(FinanceSummaryRepositoryInterface::class, EloquentFinanceSummaryRepository::class);
        $this->app->bind(StorageServiceInterface::class, function (): StorageServiceInterface {
            return match (config('filesystems.default')) {
                'local' => $this->app->make(LocalStorageService::class),
                default => $this->app->make(S3StorageService::class),
            };
        });

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
        Gate::policy(SchoolCalendarEvent::class, SchoolCalendarEventPolicy::class);
        Gate::policy(TherapistProfile::class, TherapistProfilePolicy::class);
        Gate::policy(StudentProfile::class, StudentProfilePolicy::class);
        Gate::policy(StudentComment::class, StudentCommentPolicy::class);
        Gate::policy(StudentDocument::class, StudentDocumentPolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(SessionLog::class, SessionLogPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(TherapistBill::class, TherapistBillPolicy::class);
        Gate::policy(SchoolContract::class, SchoolContractPolicy::class);
        Gate::policy(TherapistContract::class, TherapistContractPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(ServiceAlias::class, ServiceAliasPolicy::class);
        Gate::policy(ServiceSupportAgreement::class, SSAPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);

        Event::listen(ScheduleCreated::class, SendScheduleNotification::class);
        Event::listen(ScheduleUpdated::class, SendScheduleNotification::class);

        // Generate password reset URLs with ?username= instead of ?email=
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return url(route('password.reset', [
                'token' => $token,
                'username' => $user->getEmailForPasswordReset(),
            ], false));
        });

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
