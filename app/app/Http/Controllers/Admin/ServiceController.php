<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\ServiceRowTransformer;
use App\Domain\Service\Services\ServiceCatalogService;
use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
use App\DTOs\ServiceFilterDTO;
use App\DTOs\UpdateServiceDTO;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Service\ChangeServiceStatusRequest;
use App\Http\Requests\Admin\Service\ExportServicesRequest;
use App\Http\Requests\Admin\Service\IndexServiceRequest;
use App\Http\Requests\Admin\Service\ServiceDataRequest;
use App\Http\Requests\Admin\Service\StoreServiceRequest;
use App\Http\Requests\Admin\Service\UpdateServiceRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ServiceController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'services.name',
        1 => 'services.is_frequency_service',
        2 => 'services.is_direct_service',
        3 => 'services.min_duration_minutes',
        4 => 'services.status',
    ];

    public function __construct(
        private readonly ServiceCatalogService $serviceCatalog,
    ) {}

    public function index(IndexServiceRequest $request): View
    {
        $this->authorize('viewAny', Service::class);

        $filters = ServiceFilterDTO::fromArray($request->validated());
        $metrics = $this->serviceCatalog->metrics();

        return view('admin.services.index', [
            'services' => collect(),
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'statuses' => ServiceStatus::cases(),
            'datatableUrl' => route('admin.services.data'),
        ]);
    }

    public function data(ServiceDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'is_frequency_service' => $request->input('filter_is_frequency_service'),
            'is_direct_service' => $request->input('filter_is_direct_service'),
            'is_billable' => $request->input('filter_is_billable'),
        ];

        $filters = ServiceFilterDTO::fromArray($filterData);

        $result = $this->serviceCatalog->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            [ServiceRowTransformer::class, 'transform']
        );
    }

    public function create(): View
    {
        $this->authorize('create', Service::class);

        return view('admin.services.create');
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Service::class);

        $dto = CreateServiceDTO::fromArray($request->validated());
        $this->serviceCatalog->create($dto);

        return redirect()
            ->route('admin.services.index')
            ->with('status', 'Service created successfully.');
    }

    public function edit(Service $service): View
    {
        $this->authorize('update', $service);

        return view('admin.services.edit', [
            'service' => $service,
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);

        $dto = UpdateServiceDTO::fromArray($request->validated());
        $this->serviceCatalog->update($service, $dto);

        return redirect()
            ->route('admin.services.index')
            ->with('status', 'Service updated successfully.');
    }

    public function updateStatus(ChangeServiceStatusRequest $request, Service $service): JsonResponse
    {
        $this->authorize('changeStatus', $service);

        $dto = ChangeServiceStatusDTO::fromArray($request->validated());
        $service = $this->serviceCatalog->changeStatus($service, $dto);

        $message = $service->status === ServiceStatus::ACTIVE
            ? 'Service activated successfully.'
            : 'Service deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function export(ExportServicesRequest $request): StreamedResponse
    {
        $this->authorize('export', Service::class);

        $filters = ServiceFilterDTO::fromArray($request->validated());
        $services = $this->serviceCatalog->all($filters);
        $filename = sprintf('services-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($services): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Name',
                'Description',
                'Status',
                'Frequency Service',
                'Direct Service',
                'Group Service',
                'Billable',
                'Min Duration (mins)',
                'Max Duration (mins)',
            ]);

            /** @var Service $service */
            foreach ($services as $service) {
                fputcsv($handle, [
                    $service->id,
                    $service->name,
                    $service->description,
                    $service->status->value,
                    $service->is_frequency_service ? 'Yes' : 'No',
                    $service->is_direct_service ? 'Yes' : 'No',
                    $service->is_group_service ? 'Yes' : 'No',
                    $service->is_billable ? 'Yes' : 'No',
                    $service->min_duration_minutes,
                    $service->max_duration_minutes,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
