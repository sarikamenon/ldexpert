<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\QGlobRequestRowTransformer;
use App\Domain\QGlobRequest\Services\QGlobRequestService;
use App\Domain\User\Services\UserService;
use App\DTOs\QGlobRequestFilterDTO;
use App\DTOs\RespondQGlobRequestDTO;
use App\Enums\QGlobRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QGlob\QGlobRequestDataRequest;
use App\Http\Requests\Admin\QGlob\RespondQGlobRequestRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\QGlobRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        private readonly UserService $userService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', QGlobRequest::class);

        return view('admin.qglob-requests.index', [
            'statuses' => QGlobRequestStatus::cases(),
            'therapists' => $this->userService
                ->listActiveTherapistsForSelect()
                ->sortBy('name')
                ->values(),
            'datatableUrl' => route('admin.qglob-requests.data'),
        ]);
    }

    public function data(QGlobRequestDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', QGlobRequest::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filters = QGlobRequestFilterDTO::fromArray([
            'therapist_id' => $request->input('filter_therapist_id'),
            'status' => $request->input('filter_status'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
        ]);

        $result = $this->service->listForDataTables($filters, $params, null);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (QGlobRequest $row): array => QGlobRequestRowTransformer::transformForAdmin($row),
        );
    }

    public function show(QGlobRequest $qglob_request): View
    {
        $this->authorize('view', $qglob_request);

        $qglob_request->loadMissing(['requestedBy', 'student.studentProfile.school', 'respondedBy']);

        return view('admin.qglob-requests.show', [
            'qglobRequest' => $qglob_request,
        ]);
    }

    public function respond(RespondQGlobRequestRequest $request, QGlobRequest $qglob_request): RedirectResponse
    {
        $this->authorize('respond', $qglob_request);

        $validated = $request->validated();
        $decision = QGlobRequestStatus::from((string) $validated['decision']);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        $dto = new RespondQGlobRequestDTO(
            status: $decision,
            adminResponse: isset($validated['admin_response']) ? (string) $validated['admin_response'] : null,
            respondedById: $admin->id,
        );

        $this->service->respond($qglob_request, $dto);

        return redirect()
            ->route('admin.qglob-requests.show', $qglob_request)
            ->with('status', 'Request updated.');
    }
}
