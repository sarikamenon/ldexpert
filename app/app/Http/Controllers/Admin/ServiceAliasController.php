<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\ServiceAliasRowTransformer;
use App\Domain\ServiceAlias\Services\ServiceAliasCatalogService;
use App\DTOs\CreateServiceAliasDTO;
use App\DTOs\ServiceAliasFilterDTO;
use App\DTOs\UpdateServiceAliasDTO;
use App\Enums\SSAImportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceAlias\ServiceAliasDataRequest;
use App\Http\Requests\Admin\ServiceAlias\StoreServiceAliasRequest;
use App\Http\Requests\Admin\ServiceAlias\UpdateServiceAliasRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\ServiceAlias;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ServiceAliasController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'service_aliases.source',
        1 => 'service_aliases.external_name',
        3 => 'service_aliases.created_at',
    ];

    public function __construct(
        private readonly ServiceAliasCatalogService $serviceAliasCatalog,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', ServiceAlias::class);

        return view('admin.service-aliases.index', [
            'metrics' => $this->serviceAliasCatalog->getMetrics(),
            'sources' => SSAImportType::aliasSources(),
            'datatableUrl' => route('admin.service-aliases.data'),
        ]);
    }

    public function data(ServiceAliasDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceAlias::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filters = ServiceAliasFilterDTO::fromArray($request->validated());

        $result = $this->serviceAliasCatalog->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            [ServiceAliasRowTransformer::class, 'transform']
        );
    }

    public function create(): View
    {
        $this->authorize('create', ServiceAlias::class);

        return view('admin.service-aliases.create', [
            'sources' => SSAImportType::aliasSources(),
            'services' => $this->serviceAliasCatalog->listActiveServicesForSelect(),
        ]);
    }

    public function store(StoreServiceAliasRequest $request): RedirectResponse
    {
        $this->authorize('create', ServiceAlias::class);

        $dto = CreateServiceAliasDTO::fromArray($request->validated());
        $this->serviceAliasCatalog->create($dto);

        return redirect()
            ->route('admin.service-aliases.index')
            ->with('status', 'Service alias created successfully.');
    }

    public function edit(ServiceAlias $serviceAlias): View
    {
        $this->authorize('update', $serviceAlias);

        return view('admin.service-aliases.edit', [
            'alias' => $serviceAlias,
            'sources' => SSAImportType::aliasSources(),
            'services' => $this->serviceAliasCatalog->listActiveServicesForSelect(),
        ]);
    }

    public function update(UpdateServiceAliasRequest $request, ServiceAlias $serviceAlias): RedirectResponse
    {
        $this->authorize('update', $serviceAlias);

        $dto = UpdateServiceAliasDTO::fromArray($request->validated());
        $this->serviceAliasCatalog->update($serviceAlias, $dto);

        return redirect()
            ->route('admin.service-aliases.index')
            ->with('status', 'Service alias updated successfully.');
    }

    public function destroy(ServiceAlias $serviceAlias): JsonResponse
    {
        $this->authorize('delete', $serviceAlias);

        $this->serviceAliasCatalog->delete($serviceAlias);

        return response()->json([
            'success' => true,
            'message' => 'Service alias deleted successfully.',
        ]);
    }
}
