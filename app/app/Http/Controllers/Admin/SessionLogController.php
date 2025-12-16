<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogService;
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
    ) {}

    public function index(SessionLogIndexRequest $request): View
    {
        $dto = SessionLogIndexDTO::fromArray($request->validated());
        $viewData = $this->indexService->getAdminIndex($dto);

        return view('admin.session-logs.index', $viewData);
    }

    public function show(SessionLog $sessionLog): View
    {
        $sessionLog->load(['student', 'ssa', 'service', 'school', 'therapistContract', 'schoolContract', 'therapist']);

        return view('admin.session-logs.show', [
            'sessionLog' => $sessionLog,
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
        $this->authorize('update', $sessionLog);

        $data = $request->validated();

        $dtoArray = array_merge($sessionLog->toArray(), $data);

        $dto = \App\DTOs\UpdateSessionLogDTO::fromArray($dtoArray);
        $this->service->update($request->user(), $sessionLog, $dto);

        return redirect()
            ->route('admin.session-logs.show', $sessionLog)
            ->with('success', 'Session log updated successfully.');
    }

    public function finalize(SessionLog $sessionLog): RedirectResponse
    {
        $this->authorize('finalize', $sessionLog);

        $this->service->finalize(auth()->user(), $sessionLog);

        return redirect()
            ->route('admin.session-logs.show', $sessionLog)
            ->with('success', 'Session log finalized.');
    }

    public function cancel(SessionLog $sessionLog, Request $request): RedirectResponse
    {
        $this->authorize('cancel', $sessionLog);

        $reason = $request->input('cancellation_reason', 'Cancelled by admin');
        $this->service->cancel(auth()->user(), $sessionLog, $reason);

        return redirect()
            ->route('admin.session-logs.show', $sessionLog)
            ->with('success', 'Session log cancelled.');
    }
}
