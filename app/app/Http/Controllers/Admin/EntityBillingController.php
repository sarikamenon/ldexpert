<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Services\BillingScheduleService;
use App\Domain\Billing\Services\BillingSettingsService;
use App\DTOs\BillingScheduleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntityBillingRequest;
use App\Models\BillingSchedule;
use Illuminate\Http\JsonResponse;

final class EntityBillingController extends Controller
{
    /** @var array<string, array{schedulable_type: string, schedule_type: string}> */
    private const ENTITY_MAP = [
        'school' => [
            'schedulable_type' => 'App\\Models\\School',
            'schedule_type' => 'school_invoice',
        ],
        'therapist' => [
            'schedulable_type' => 'App\\Models\\User',
            'schedule_type' => 'therapist_bill',
        ],
        'private_student' => [
            'schedulable_type' => 'App\\Models\\User',
            'schedule_type' => 'private_student_invoice',
        ],
    ];

    public function __construct(
        private readonly BillingScheduleService $scheduleService,
        private readonly BillingSettingsService $settingsService,
    ) {}

    public function show(string $entityType, int $entityId): JsonResponse
    {
        $this->authorize('viewAny', BillingSchedule::class);

        $mapping = self::ENTITY_MAP[$entityType] ?? null;
        if ($mapping === null) {
            return response()->json(['error' => 'Invalid entity type.'], 422);
        }

        $schedule = $this->scheduleService->getEntityConfig(
            $mapping['schedulable_type'],
            $entityId,
            $mapping['schedule_type'],
        );

        if ($schedule !== null) {
            return response()->json([
                'is_default' => false,
                'data' => $this->formatSchedule($schedule),
            ]);
        }

        $settings = $this->settingsService->getSettings();

        return response()->json([
            'is_default' => true,
            'data' => [
                'billing_mode' => 'standard',
                'frequency' => $settings->default_frequency->value,
                'generation_day_type' => $settings->default_generation_day_type->value,
                'generation_day_of_week' => $settings->default_generation_day_of_week,
                'generation_delay_days' => null,
                'min_grace_days' => $settings->default_min_grace_days,
                'payment_terms_days' => $settings->default_payment_terms_days,
                'auto_generate' => $settings->default_auto_generate,
                'auto_send' => $settings->default_auto_send,
                'notes' => null,
            ],
        ]);
    }

    public function storeOrUpdate(StoreEntityBillingRequest $request): JsonResponse
    {
        $this->authorize('create', BillingSchedule::class);

        $entityType = $request->validated('entity_type');
        $entityId = (int) $request->validated('entity_id');
        $mapping = self::ENTITY_MAP[$entityType];

        $existing = $this->scheduleService->getEntityConfig(
            $mapping['schedulable_type'],
            $entityId,
            $mapping['schedule_type'],
        );

        $dtoData = [
            'schedulable_type' => $mapping['schedulable_type'],
            'schedulable_id' => $entityId,
            'schedule_type' => $mapping['schedule_type'],
            'billing_mode' => $request->validated('billing_mode'),
            'frequency' => $request->validated('frequency'),
            'generation_day_type' => $request->validated('generation_day_type'),
            'generation_day_of_week' => $request->validated('generation_day_of_week'),
            'generation_delay_days' => $request->validated('generation_delay_days'),
            'min_grace_days' => $request->validated('min_grace_days'),
            'payment_terms_days' => $request->validated('payment_terms_days'),
            'auto_generate' => $request->validated('auto_generate'),
            'auto_send' => $request->validated('auto_send'),
            'notes' => $request->validated('notes'),
        ];

        $dto = BillingScheduleDTO::fromArray($dtoData);

        if ($existing !== null) {
            $schedule = $this->scheduleService->updateSchedule($existing, $dto);
            $message = 'Billing configuration updated.';
        } else {
            $schedule = $this->scheduleService->createSchedule($dto);
            $message = 'Custom billing configuration saved.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->formatSchedule($schedule),
        ]);
    }

    public function destroy(string $entityType, int $entityId): JsonResponse
    {
        $this->authorize('create', BillingSchedule::class);

        $mapping = self::ENTITY_MAP[$entityType] ?? null;
        if ($mapping === null) {
            return response()->json(['error' => 'Invalid entity type.'], 422);
        }

        $schedule = $this->scheduleService->getEntityConfig(
            $mapping['schedulable_type'],
            $entityId,
            $mapping['schedule_type'],
        );

        if ($schedule === null) {
            return response()->json(['error' => 'No custom configuration found.'], 404);
        }

        $this->scheduleService->deleteSchedule($schedule);

        return response()->json([
            'success' => true,
            'message' => 'Custom configuration removed. Global defaults will be used.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSchedule(BillingSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'billing_mode' => $schedule->billing_mode->value,
            'frequency' => $schedule->frequency->value,
            'generation_day_type' => $schedule->generation_day_type->value,
            'generation_day_of_week' => $schedule->generation_day_of_week,
            'generation_delay_days' => $schedule->generation_delay_days,
            'min_grace_days' => $schedule->min_grace_days,
            'payment_terms_days' => $schedule->payment_terms_days,
            'auto_generate' => $schedule->auto_generate,
            'auto_send' => $schedule->auto_send,
            'notes' => $schedule->notes,
            'is_active' => $schedule->is_active,
            'next_run_at' => $schedule->next_run_at?->toDateString(),
            'last_run_at' => $schedule->last_run_at?->toDateTimeString(),
        ];
    }
}
