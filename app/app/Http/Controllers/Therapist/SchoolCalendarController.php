<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\SSA\Services\SSAService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\SchoolCalendarEventsRequest;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
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

        return view('therapist.school-calendar.index', [
            'schools' => $this->ssaService->getSchoolsForTherapist($therapist->id),
            'selectedDate' => CarbonImmutable::today(),
        ]);
    }

    public function events(SchoolCalendarEventsRequest $request, School $school): JsonResponse
    {
        $this->authorize('viewAny', SchoolCalendarEvent::class);

        try {
            /** @var User $therapist */
            $therapist = $request->user();

            if (! $this->ssaService->therapistHasAccessToSchool($therapist->id, $school->id)) {
                abort(403);
            }

            $start = $request->input('start')
                ? CarbonImmutable::parse((string) $request->input('start'))
                : CarbonImmutable::today()->startOfMonth();
            $end = $request->input('end')
                ? CarbonImmutable::parse((string) $request->input('end'))
                : CarbonImmutable::today()->endOfMonth();

            $events = $this->calendarService->listBySchoolAndRangeAsDTO($school->id, $start, $end);

            return response()->json([
                'events' => $events->map->toArray()->values(),
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
}
