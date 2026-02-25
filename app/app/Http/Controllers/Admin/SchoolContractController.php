<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\SchoolContractRowTransformer;
use App\Domain\Contract\Services\SchoolContractService;
use App\Domain\School\Services\SchoolService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\DTOs\ChangeContractStatusDTO;
use App\DTOs\CreateSchoolContractDTO;
use App\DTOs\SchoolContractFilterDTO;
use App\DTOs\UpdateSchoolContractDTO;
use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Exceptions\ContractOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contract\ChangeSchoolContractStatusRequest;
use App\Http\Requests\Admin\Contract\IndexSchoolContractRequest;
use App\Http\Requests\Admin\Contract\SchoolContractDataRequest;
use App\Http\Requests\Admin\Contract\StoreSchoolContractRequest;
use App\Http\Requests\Admin\Contract\UpdateSchoolContractRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\SchoolContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class SchoolContractController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'school_contracts.id',
        1 => 'school_contracts.start_date',
        2 => 'school_contracts.end_date',
        3 => 'school_contracts.status',
    ];

    public function __construct(
        private readonly SchoolContractService $service,
        private readonly SchoolService $schoolService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(IndexSchoolContractRequest $request): View
    {
        $this->authorize('viewAny', SchoolContract::class);

        return view('admin.contracts.schools.index', [
            'contracts' => collect(),
            'metrics' => $this->service->metrics(),
            'filters' => $request->validated(),
            'statuses' => ContractStatus::cases(),
            'schools' => $this->schoolService->listActiveForSelect(),
            'datatableUrl' => route('admin.contracts.schools.data'),
        ]);
    }

    public function data(SchoolContractDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SchoolContract::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filterData = [
            'status' => $request->input('filter_status'),
            'school_ids' => $request->input('filter_school_ids', []),
        ];
        $filters = SchoolContractFilterDTO::fromArray($filterData);

        $result = $this->service->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (SchoolContract $contract): array => SchoolContractRowTransformer::transform($contract),
        );
    }

    public function create(): View
    {
        $this->authorize('create', SchoolContract::class);

        return view('admin.contracts.schools.create', $this->formData());
    }

    public function store(StoreSchoolContractRequest $request): RedirectResponse
    {
        $this->authorize('create', SchoolContract::class);

        $dto = CreateSchoolContractDTO::fromArray($request->validated());

        try {
            $this->service->create($dto);
        } catch (ContractOverlapException $exception) {
            return back()
                ->withErrors(['start_date' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.contracts.schools.index')
            ->with('status', 'School contract created successfully.');
    }

    public function show(SchoolContract $schoolContract): View
    {
        $this->authorize('view', $schoolContract);

        return view('admin.contracts.schools.show', [
            'contract' => $schoolContract->load(['school', 'services.service']),
        ]);
    }

    public function edit(SchoolContract $schoolContract): View
    {
        $this->authorize('update', $schoolContract);

        return view('admin.contracts.schools.edit', [
            'contract' => $schoolContract->load(['school', 'services']),
        ] + $this->formData());
    }

    public function update(UpdateSchoolContractRequest $request, SchoolContract $schoolContract): RedirectResponse
    {
        $this->authorize('update', $schoolContract);

        $dto = UpdateSchoolContractDTO::fromArray($request->validated(), $schoolContract->status);

        try {
            $this->service->update($schoolContract, $dto);
        } catch (ContractOverlapException $exception) {
            return back()
                ->withErrors(['start_date' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.contracts.schools.index')
            ->with('status', 'School contract updated successfully.');
    }

    public function updateStatus(ChangeSchoolContractStatusRequest $request, SchoolContract $schoolContract): JsonResponse
    {
        $this->authorize('changeStatus', $schoolContract);

        $dto = ChangeContractStatusDTO::fromArray($request->validated());

        try {
            $contract = $this->service->changeStatus($schoolContract, $dto);
        } catch (ContractOverlapException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $message = $contract->status === ContractStatus::ACTIVE
            ? 'Contract activated successfully.'
            : 'Contract deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'status' => $contract->status->value,
        ]);
    }

    private function formData(): array
    {
        return [
            'schools' => $this->schoolService->listActiveForSelect(),
            'services' => $this->serviceCatalogService->listActiveForSelect(),
            'statuses' => ContractStatus::cases(),
            'rateTypes' => RateType::cases(),
        ];
    }
}
