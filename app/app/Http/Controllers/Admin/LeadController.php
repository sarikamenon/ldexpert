<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\DataTables\Transformers\LeadRowTransformer;
use App\Domain\Lead\Services\LeadService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\ChangeLeadStatusDTO;
use App\DTOs\ConvertLeadDTO;
use App\DTOs\CreateLeadDTO;
use App\DTOs\LeadFilterDTO;
use App\DTOs\UpdateLeadDTO;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lead\ChangeLeadStatusRequest;
use App\Http\Requests\Admin\Lead\ConvertLeadRequest;
use App\Http\Requests\Admin\Lead\LeadDataRequest;
use App\Http\Requests\Admin\Lead\StoreLeadRequest;
use App\Http\Requests\Admin\Lead\UpdateLeadRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class LeadController extends Controller
{
    use DataTablesResponse;

    /** Column index => allowed order column for server-side DataTables. */
    private const LEADS_ORDER_WHITELIST = [
        0 => 'leads.id',
        1 => 'leads.last_name',
        2 => 'leads.email',
        3 => 'schools.display_name',
        4 => 'leads.source',
        5 => 'leads.status',
        6 => 'leads.follow_up_date',
        7 => 'leads.created_at',
    ];

    public function __construct(
        private readonly LeadService $leadService,
        private readonly SchoolRepositoryInterface $schoolRepository,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Lead::class);

        $metrics = $this->leadService->getMetrics();

        return view('admin.leads.index', [
            'metrics' => $metrics,
            'statuses' => LeadStatus::cases(),
            'sources' => LeadSource::cases(),
            'schools' => $this->schoolRepository->listAllForSelect(),
            'datatableUrl' => route('admin.leads.data'),
        ]);
    }

    public function data(LeadDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $params = DataTablesRequest::fromRequest($request, self::LEADS_ORDER_WHITELIST);
        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'source' => $request->input('filter_source'),
            'school_id' => $request->input('filter_school_id'),
        ];
        $filters = LeadFilterDTO::fromArray($filterData);

        $result = $this->leadService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (Lead $lead): array => LeadRowTransformer::transform($lead),
        );
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('admin.leads.create', $this->formData());
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $validated = $request->validated();
        $validated['created_by'] = $request->user()?->id;

        $dto = CreateLeadDTO::fromArray($validated);
        $lead = $this->leadService->create($dto);

        return redirect()
            ->route('admin.leads.show', $lead)
            ->with('status', 'Lead created successfully.');
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead = $this->leadService->findWithNotes((int) $lead->id);
        abort_if($lead === null, 404);

        return view('admin.leads.show', [
            'lead' => $lead,
            'statuses' => LeadStatus::cases(),
        ]);
    }

    public function edit(Lead $lead): View
    {
        $this->authorize('update', $lead);

        return view('admin.leads.edit', array_merge(
            ['lead' => $lead],
            $this->formData()
        ));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $dto = UpdateLeadDTO::fromArray($request->validated());
        $this->leadService->update($lead, $dto);

        return redirect()
            ->route('admin.leads.show', $lead)
            ->with('status', 'Lead updated successfully.');
    }

    public function updateStatus(ChangeLeadStatusRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('changeStatus', $lead);

        $dto = ChangeLeadStatusDTO::fromArray($request->validated());
        $updatedLead = $this->leadService->changeStatus($lead, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Lead status updated to '.$updatedLead->status->label().'.',
        ]);
    }

    public function showConvertForm(Lead $lead): View
    {
        $this->authorize('convert', $lead);

        return view('admin.leads.convert', array_merge(
            ['lead' => $lead],
            $this->conversionFormData()
        ));
    }

    public function convert(ConvertLeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('convert', $lead);

        $validated = $request->validated();
        $validated['password'] = Str::password(12);

        $dto = ConvertLeadDTO::fromArray($validated);
        $profile = $this->leadService->convertToStudent($lead, $dto);

        return redirect()
            ->route('admin.students.show', $profile->user_id)
            ->with('status', 'Lead converted to student successfully.');
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $this->leadService->delete($lead);

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully.',
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'schools' => $this->schoolRepository->listAllForSelect(),
            'sources' => LeadSource::cases(),
            'states' => UsStates::STATES,
            'genders' => ['Male', 'Female', 'Non-binary', 'Prefer not to say'],
        ];
    }

    /** @return array<string, mixed> */
    private function conversionFormData(): array
    {
        return [
            'schools' => $this->schoolRepository->listAllForSelect(),
            'states' => UsStates::STATES,
            'timezones' => UsTimezones::TIMEZONES,
            'genders' => ['Male', 'Female', 'Non-binary', 'Prefer not to say'],
        ];
    }
}
