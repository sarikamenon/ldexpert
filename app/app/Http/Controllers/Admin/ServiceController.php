<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Service\Services\ServiceCatalogService;
use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
use App\DTOs\ServiceFilterDTO;
use App\DTOs\UpdateServiceDTO;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Service\ChangeServiceStatusRequest;
use App\Http\Requests\Admin\Service\IndexServiceRequest;
use App\Http\Requests\Admin\Service\StoreServiceRequest;
use App\Http\Requests\Admin\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceCatalogService $serviceCatalog,
    ) {}

    public function index(IndexServiceRequest $request): View
    {
        $this->authorize('viewAny', Service::class);

        $filters = ServiceFilterDTO::fromArray($request->validated());
        $services = $this->serviceCatalog->paginate($filters);
        $metrics = $this->serviceCatalog->metrics();

        return view('admin.services.index', [
            'services' => $services,
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'statuses' => ServiceStatus::cases(),
        ]);
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
}
