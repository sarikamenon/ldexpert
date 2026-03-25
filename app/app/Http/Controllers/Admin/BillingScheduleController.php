<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\BillingScheduleRowTransformer;
use App\DataTables\Transformers\BillingScheduleRunRowTransformer;
use App\Domain\Billing\Services\BillingAutomationService;
use App\Domain\Billing\Services\BillingScheduleService;
use App\DTOs\BillingScheduleDTO;
use App\DTOs\BillingScheduleFilterDTO;
use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\GenerationDayType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BillingScheduleDataRequest;
use App\Http\Requests\Admin\BillingScheduleHistoryDataRequest;
use App\Http\Requests\Admin\StoreBillingScheduleRequest;
use App\Http\Requests\Admin\UpdateBillingScheduleRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class BillingScheduleController extends Controller
{
    use DataTablesResponse;

    private const ORDER_WHITELIST = [
        0 => 'schedule_type',
        1 => 'billing_mode',
        2 => 'frequency',
        3 => 'next_run_at',
        4 => 'last_run_at',
        5 => 'is_active',
    ];

    /** @var array<int, string> */
    private const RUN_HISTORY_ORDER_ADVANCE = [
        0 => 'billing_period_end',
        1 => 'generation_date',
        2 => 'sessions_found',
        3 => 'adjustments_count',
        4 => 'carry_forward_amount',
        5 => 'total_amount',
        6 => 'status',
    ];

    /** @var array<int, string> */
    private const RUN_HISTORY_ORDER_STANDARD = [
        0 => 'billing_period_end',
        1 => 'generation_date',
        2 => 'sessions_found',
        3 => 'sessions_from_prior_periods',
        4 => 'total_amount',
        5 => 'status',
    ];

    public function __construct(
        private readonly BillingScheduleService $scheduleService,
        private readonly BillingAutomationService $automationService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BillingSchedule::class);

        return view('admin.billing.schedules.index', [
            'filters' => $request->all(),
            'scheduleTypes' => BillingScheduleType::cases(),
            'billingModes' => BillingMode::cases(),
            'datatableUrl' => route('admin.billing.schedules.data'),
        ]);
    }

    public function data(BillingScheduleDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', BillingSchedule::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $filters = BillingScheduleFilterDTO::fromArray([
            'schedule_type' => $request->input('filter_schedule_type'),
            'billing_mode' => $request->input('filter_billing_mode'),
            'is_active' => $request->input('filter_is_active'),
            'frequency' => $request->input('filter_frequency'),
        ]);

        $result = $this->scheduleService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (BillingSchedule $schedule): array => BillingScheduleRowTransformer::transform($schedule),
        );
    }

    public function create(): View
    {
        $this->authorize('create', BillingSchedule::class);

        return view('admin.billing.schedules.create', [
            'frequencies' => BillingFrequency::cases(),
            'generationDayTypes' => GenerationDayType::cases(),
            'scheduleTypes' => BillingScheduleType::cases(),
            'billingModes' => BillingMode::cases(),
        ]);
    }

    public function store(StoreBillingScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create', BillingSchedule::class);

        $dto = BillingScheduleDTO::fromArray($request->validated());
        $schedule = $this->scheduleService->createSchedule($dto);

        return redirect()
            ->route('admin.billing.schedules.index')
            ->with('success', "Billing schedule #{$schedule->id} created successfully.");
    }

    public function edit(BillingSchedule $schedule): View
    {
        $this->authorize('update', $schedule);

        return view('admin.billing.schedules.edit', [
            'schedule' => $schedule,
            'frequencies' => BillingFrequency::cases(),
            'generationDayTypes' => GenerationDayType::cases(),
            'scheduleTypes' => BillingScheduleType::cases(),
            'billingModes' => BillingMode::cases(),
        ]);
    }

    public function update(UpdateBillingScheduleRequest $request, BillingSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $dto = BillingScheduleDTO::fromArray($request->validated());
        $this->scheduleService->updateSchedule($schedule, $dto);

        return redirect()
            ->route('admin.billing.schedules.index')
            ->with('success', 'Billing schedule updated successfully.');
    }

    public function toggleActive(BillingSchedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);

        $schedule = $this->scheduleService->toggleActive($schedule);

        return response()->json([
            'success' => true,
            'is_active' => $schedule->is_active,
            'message' => $schedule->is_active ? 'Schedule activated.' : 'Schedule deactivated.',
        ]);
    }

    public function runNow(BillingSchedule $schedule): JsonResponse
    {
        $this->authorize('runNow', $schedule);

        try {
            $result = $this->automationService->processSingleSchedule($schedule);

            return response()->json([
                'success' => true,
                'result' => $result->toArray(),
                'message' => "Schedule processed: {$result->status}",
            ]);
        } catch (\Throwable $e) {
            Log::error('Manual billing run failed', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Run failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function runHistory(BillingSchedule $schedule): View
    {
        $this->authorize('view', $schedule);

        return view('admin.billing.schedules.history', [
            'schedule' => $schedule->load('schedulable'),
            'datatableUrl' => route('admin.billing.schedules.history.data', $schedule),
        ]);
    }

    public function runHistoryData(BillingScheduleHistoryDataRequest $request, BillingSchedule $schedule): JsonResponse
    {
        $this->authorize('view', $schedule);

        $whitelist = $schedule->isAdvanceMode()
            ? self::RUN_HISTORY_ORDER_ADVANCE
            : self::RUN_HISTORY_ORDER_STANDARD;

        $params = DataTablesRequest::fromRequest($request, $whitelist);
        $result = $this->scheduleService->listRunsForDataTables($schedule->id, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            fn (BillingScheduleRun $run): array => BillingScheduleRunRowTransformer::transform($run, $schedule),
        );
    }
}
