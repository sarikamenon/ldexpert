<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\TherapistSSARowTransformer;
use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\SSA\Services\SSAMinutesSummaryService;
use App\Domain\SSA\Services\SSAService;
use App\DTOs\SessionLogIndexDTO;
use App\DTOs\SSAFilterDTO;
use App\Enums\SSAStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\TherapistSSADataRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SSAController extends Controller
{
    use DataTablesResponse;

    /** @var array<int, string> */
    private const ORDER_WHITELIST = [
        0 => 'service_support_agreements.id',
        1 => 'students.name',
        2 => 'therapists.name',
        3 => 'service_support_agreements.start_date',
        4 => 'service_support_agreements.minutes_per_session',
        5 => 'service_support_agreements.tho_minutes',
        6 => 'service_support_agreements.status',
    ];

    public function __construct(
        private readonly SSAService $ssaService,
        private readonly SessionLogIndexService $sessionLogIndexService,
        private readonly SSAMinutesSummaryService $ssaMinutesSummaryService,
    ) {}

    public function index(Request $request): View
    {
        return view('therapist.ssas.index', [
            'ssas' => collect(),
            'filters' => $request->query(),
            'statuses' => SSAStatus::cases(),
            'datatableUrl' => route('therapist.ssas.data'),
        ]);
    }

    public function data(TherapistSSADataRequest $request): JsonResponse
    {
        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'therapist_id' => $therapist->id,
        ];
        $filters = SSAFilterDTO::fromArray($filterData);

        $result = $this->ssaService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (ServiceSupportAgreement $ssa): array => TherapistSSARowTransformer::transform($ssa),
        );
    }

    public function show(Request $request, ServiceSupportAgreement $ssa): View
    {
        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        // Ensure therapist can only view SSAs assigned to them
        if ($ssa->assigned_therapist_id !== $therapist->id) {
            abort(403, 'You do not have access to this SSA.');
        }

        $activeTab = $request->query('tab', 'dashboard');

        $ssa->load([
            'student',
            'student.studentProfile.school',
            'primaryService',
            'additionalServices',
            'assignedTherapist',
            'assignedTherapist.therapistProfile',
        ]);

        $viewData = [
            'ssa' => $ssa,
            'activeTab' => $activeTab,
        ];

        if ($activeTab === 'dashboard') {
            $viewData['minutesSummary'] = $this->ssaMinutesSummaryService->getMinutesSummaryForSSA($ssa);
        }

        // Load assignment history if needed
        if ($activeTab === 'assignment') {
            $ssa->load([
                'assignmentHistory.therapist',
                'assignmentHistory.assignedBy',
            ]);
            $viewData['assignmentHistory'] = $this->ssaService->getAssignmentHistory($ssa)->withUserTimezone();
        } elseif ($activeTab === 'session_logs') {
            $dto = SessionLogIndexDTO::fromArray(
                array_merge($request->query(), ['ssa_id' => $ssa->id, 'therapist_id' => $therapist->id])
            );
            $sessionLogData = $this->sessionLogIndexService->getTherapistIndex($therapist, $dto);

            $viewData['sessionLogs'] = $sessionLogData['sessionLogs'];
            $viewData['sessionLogColumns'] = $sessionLogData['columns'];
            $viewData['sessionLogRows'] = $sessionLogData['rows'];
            $viewData['sessionLogStatuses'] = $sessionLogData['statuses'];
            $viewData['sessionLogFilters'] = $request->query();
        }

        return view('therapist.ssas.show', $viewData);
    }
}
