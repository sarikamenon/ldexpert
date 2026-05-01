<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\SSA\Services\SSAService;
use App\Enums\SchoolCalendarEventType;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class SchoolCalendarController extends Controller
{
    public function __construct(
        private readonly SSAService $ssaService,
        private readonly SchoolCalendarService $calendarService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SchoolCalendarEvent::class);

        /** @var User $therapist */
        $therapist = $request->user();

        $schools = $this->getTherapistSchools($therapist->id);

        return view('therapist.school-calendar.index', [
            'schools' => $schools,
            'selectedDate' => CarbonImmutable::today(),
        ]);
    }

    public function events(Request $request, School $school): JsonResponse
    {
        $this->authorize('viewAny', SchoolCalendarEvent::class);

        try {
            /** @var User $therapist */
            $therapist = $request->user();

            $allowedSchoolIds = $this->getTherapistSchools($therapist->id)->pluck('id')->all();
            if (! in_array($school->id, $allowedSchoolIds, true)) {
                abort(403);
            }

            $start = $request->query('start')
                ? CarbonImmutable::parse((string) $request->query('start'))
                : CarbonImmutable::today()->startOfMonth();
            $end = $request->query('end')
                ? CarbonImmutable::parse((string) $request->query('end'))
                : CarbonImmutable::today()->endOfMonth();

            $events = $this->calendarService->listBySchoolAndRange($school->id, $start, $end);

            return response()->json([
                'events' => $events->map(static function (SchoolCalendarEvent $event): array {
                    return [
                        'id' => $event->id,
                        'school_id' => $event->school_id,
                        'title' => $event->title,
                        'event_type' => $event->event_type->value,
                        'event_type_label' => $event->event_type->label(),
                        'start_date' => $event->start_date->format('Y-m-d'),
                        'end_date' => $event->end_date->format('Y-m-d'),
                        'notes' => $event->notes,
                        'is_holiday' => $event->event_type === SchoolCalendarEventType::HOLIDAY,
                    ];
                })->values(),
            ]);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to load therapist school calendar events', [
                'therapist_id' => $request->user()?->id,
                'school_id' => $school->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to load calendar events.'], 500);
        }
    }

    /**
     * Schools that have at least one active SSA assigned to this therapist.
     *
     * @return \Illuminate\Support\Collection<int, School>
     */
    private function getTherapistSchools(int $therapistId): \Illuminate\Support\Collection
    {
        $studentIds = $this->ssaService
            ->getActiveSSAsForTherapist($therapistId)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($studentIds)) {
            return collect();
        }

        $schoolIds = StudentProfile::query()
            ->whereIn('user_id', $studentIds)
            ->whereNotNull('school_id')
            ->pluck('school_id')
            ->unique()
            ->all();

        if (empty($schoolIds)) {
            return collect();
        }

        return School::query()
            ->whereIn('id', $schoolIds)
            ->orderByRaw('COALESCE(display_name, full_name) asc')
            ->get();
    }
}
