<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentDocumentService;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogService;
use App\Domain\User\Services\UserService;
use App\DTOs\SessionLogIndexDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSessionLogRequest;
use App\Http\Requests\SessionLog\SessionLogIndexRequest;
use App\Models\SessionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SessionLogController extends Controller
{
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
        $dto = SessionLogIndexDTO::fromArray($request->validated());
        $viewData = $this->indexService->getAdminIndex($dto);

        return view('admin.session-logs.index', $viewData + [
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
        ]);
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
            $this->service->update($request->user(), $sessionLog, $dto);

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
            $this->service->approve($request->user(), $sessionLog);

            return redirect()
                ->route('admin.session-logs.show', $sessionLog)
                ->with('success', 'Session log approved.');
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
            $this->service->cancel($request->user(), $sessionLog, $reason);

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
