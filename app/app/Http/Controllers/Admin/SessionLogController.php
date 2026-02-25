<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\SessionLogRowTransformer;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentDocumentService;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogService;
use App\Domain\User\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendBackSessionLogRequest;
use App\Http\Requests\Admin\SessionLog\SessionLogDataRequest;
use App\Http\Requests\Admin\UpdateSessionLogRequest;
use App\Http\Requests\SessionLog\SessionLogIndexRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\SessionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SessionLogController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'session_logs.session_date',
        4 => 'session_logs.school_invoice_amount',
        5 => 'session_logs.therapist_billable_amount',
        6 => 'session_logs.status',
    ];

    public function __construct(
        private readonly SessionLogRepositoryInterface $repository,
        private readonly SessionLogService $service,
        private readonly SessionLogIndexService $indexService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly SSAService $ssaService,
        private readonly StudentDocumentService $documentService,
    ) {}

    public function index(SessionLogIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.session-logs.index', [
            'sessionLogs' => collect(),
            'columns' => [],
            'rows' => [],
            'statuses' => \App\Enums\SessionLogStatus::cases(),
            'filters' => $filters,
            'schools' => \App\Models\School::query()
                ->active()
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
            'students' => $this->userService
                ->listActiveStudentsForSelect()
                ->sortBy('name')
                ->values(),
            'therapists' => $this->userService
                ->listActiveTherapistsForSelect()
                ->sortBy('name')
                ->values(),
            'services' => $this->serviceCatalogService
                ->listActiveForSelect()
                ->sortBy('name')
                ->values(),
            'ssas' => \App\Models\ServiceSupportAgreement::query()
                ->with(['student', 'primaryService'])
                ->orderBy('created_at', 'desc')
                ->limit(500)
                ->get(),
            'datatableUrl' => route('admin.session-logs.data'),
        ]);
    }

    public function data(SessionLogDataRequest $request): JsonResponse
    {
        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filters = [
            'school_id' => $request->input('filter_school_id'),
            'student_id' => $request->input('filter_student_id'),
            'therapist_id' => $request->input('filter_therapist_id'),
            'service_id' => $request->input('filter_service_id'),
            'ssa_id' => $request->input('filter_ssa_id'),
            'status' => $request->input('filter_status'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $result = $this->indexService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (SessionLog $log): array => SessionLogRowTransformer::transform($log),
        );
    }

    public function show(SessionLog $sessionLog): View
    {
        $sessionLog->load(['student', 'ssa', 'service', 'school', 'therapistContract', 'schoolContract', 'therapist']);

        $documents = $this->documentService->listBySessionLog($sessionLog->id);

        return view('admin.session-logs.show', [
            'sessionLog' => $sessionLog,
            'documents' => $documents,
        ]);
    }

    public function edit(SessionLog $sessionLog): View
    {
        $sessionLog->load(['student', 'ssa', 'service', 'school']);

        return view('admin.session-logs.edit', [
            'sessionLog' => $sessionLog,
        ]);
    }

    public function update(UpdateSessionLogRequest $request, SessionLog $sessionLog): RedirectResponse
    {
        $this->authorize('view', $sessionLog);

        $data = $request->validated();
        $dto = \App\DTOs\UpdateSessionLogDTO::fromArray($data);

        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $this->service->update($user, $sessionLog, $dto);

            return redirect()
                ->route('admin.session-logs.show', $sessionLog)
                ->with('success', 'Session log updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function approve(Request $request, SessionLog $sessionLog): RedirectResponse
    {
        $this->authorize('approve', $sessionLog);

        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $this->service->approve($user, $sessionLog);

            return redirect()
                ->route('admin.session-logs.show', $sessionLog)
                ->with('success', 'Session log approved.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function sendBack(SendBackSessionLogRequest $request, SessionLog $sessionLog): RedirectResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $this->service->sendBack($user, $sessionLog, $request->validated('comment'));

            return redirect()
                ->route('admin.session-logs.show', $sessionLog)
                ->with('success', 'Session log sent back to therapist for rectification.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancel(SessionLog $sessionLog, Request $request): RedirectResponse
    {
        $this->authorize('cancel', $sessionLog);

        $reason = $request->input('cancellation_reason', 'Cancelled by admin');

        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $this->service->cancel($user, $sessionLog, $reason);

            return redirect()
                ->route('admin.session-logs.show', $sessionLog)
                ->with('success', 'Session log cancelled.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
