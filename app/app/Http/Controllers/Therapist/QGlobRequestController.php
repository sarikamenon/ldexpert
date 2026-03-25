<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\QGlobRequestRowTransformer;
use App\Domain\QGlobRequest\Services\QGlobRequestService;
use App\DTOs\CreateQGlobRequestDTO;
use App\DTOs\QGlobRequestFilterDTO;
use App\Enums\QGlobRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\QGlob\StoreQGlobRequestRequest;
use App\Http\Requests\Therapist\QGlob\TherapistQGlobRequestDataRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\QGlobRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class QGlobRequestController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'qglob_requests.requested_date',
    ];

    public function __construct(
        private readonly QGlobRequestService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QGlobRequest::class);

        return view('therapist.qglob-requests.index', [
            'statuses' => QGlobRequestStatus::cases(),
            'datatableUrl' => route('therapist.qglob-requests.data'),
        ]);
    }

    public function data(TherapistQGlobRequestDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', QGlobRequest::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filters = QGlobRequestFilterDTO::fromArray([
            'status' => $request->input('filter_status'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
        ]);

        $result = $this->service->listForDataTables($filters, $params, $therapist->id);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (QGlobRequest $row): array => QGlobRequestRowTransformer::transformForTherapist($row),
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', QGlobRequest::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $students = $this->service->listEligibleStudentsForTherapist($therapist->id);

        return view('therapist.qglob-requests.create', [
            'students' => $students,
        ]);
    }

    public function store(StoreQGlobRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', QGlobRequest::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $validated = $request->validated();

        $dto = new CreateQGlobRequestDTO(
            requestedById: $therapist->id,
            studentId: (int) $validated['student_id'],
            requestedDate: (string) $validated['requested_date'],
            requestedTime: (string) $validated['requested_time'],
            note: isset($validated['note']) ? (string) $validated['note'] : null,
        );

        $this->service->create($dto);

        return redirect()
            ->route('therapist.qglob-requests.index')
            ->with('status', 'QGlob request submitted successfully.');
    }

    public function show(Request $request, QGlobRequest $qglob_request): View
    {
        $this->authorize('view', $qglob_request);

        if ((int) $qglob_request->requested_by_id !== $request->user()->id) {
            abort(403);
        }

        $qglob_request->loadMissing(['student.studentProfile.school', 'respondedBy']);

        return view('therapist.qglob-requests.show', [
            'qglobRequest' => $qglob_request,
        ]);
    }
}
