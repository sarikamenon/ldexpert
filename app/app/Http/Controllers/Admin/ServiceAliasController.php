<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\ServiceAliasRowTransformer;
use App\Enums\SSAImportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceAlias\ServiceAliasDataRequest;
use App\Http\Requests\Admin\ServiceAlias\StoreServiceAliasRequest;
use App\Http\Requests\Admin\ServiceAlias\UpdateServiceAliasRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\Service;
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

    public function index(): View
    {
        $this->authorize('viewAny', ServiceAlias::class);

        $metrics = $this->getMetrics();

        return view('admin.service-aliases.index', [
            'metrics' => $metrics,
            'sources' => SSAImportType::cases(),
            'datatableUrl' => route('admin.service-aliases.data'),
        ]);
    }

    public function data(ServiceAliasDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceAlias::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $query = ServiceAlias::query()->with('service');

        // Filter by source
        $source = $request->input('filter_source');
        if ($source !== null && $source !== '') {
            $query->where('source', $source);
        }

        // Filter by search (external_name or service name)
        $search = $request->input('filter_search');
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('external_name', 'like', "%{$search}%")
                    ->orWhereHas('service', function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                    });
            });
        }

        $recordsTotal = ServiceAlias::count();
        $recordsFiltered = $query->count();

        // Ordering
        if ($params->orderColumn !== null) {
            $query->orderBy($params->orderColumn, $params->orderDir);
        } else {
            $query->orderBy('source')->orderBy('external_name');
        }

        $rows = $query->offset($params->start)->limit($params->length)->get();

        return $this->dataTablesResponse(
            $params,
            $recordsTotal,
            $recordsFiltered,
            $rows,
            [ServiceAliasRowTransformer::class, 'transform']
        );
    }

    public function create(): View
    {
        $this->authorize('create', ServiceAlias::class);

        return view('admin.service-aliases.create', [
            'sources' => SSAImportType::cases(),
            'services' => Service::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreServiceAliasRequest $request): RedirectResponse
    {
        $this->authorize('create', ServiceAlias::class);

        ServiceAlias::create([
            'source' => $request->validated('source'),
            'external_name' => trim($request->validated('external_name')),
            'service_id' => $request->validated('service_id'),
        ]);

        return redirect()
            ->route('admin.service-aliases.index')
            ->with('status', 'Service alias created successfully.');
    }

    public function edit(ServiceAlias $serviceAlias): View
    {
        $this->authorize('update', $serviceAlias);

        return view('admin.service-aliases.edit', [
            'alias' => $serviceAlias,
            'sources' => SSAImportType::cases(),
            'services' => Service::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateServiceAliasRequest $request, ServiceAlias $serviceAlias): RedirectResponse
    {
        $this->authorize('update', $serviceAlias);

        $serviceAlias->update([
            'source' => $request->validated('source'),
            'external_name' => trim($request->validated('external_name')),
            'service_id' => $request->validated('service_id'),
        ]);

        return redirect()
            ->route('admin.service-aliases.index')
            ->with('status', 'Service alias updated successfully.');
    }

    public function destroy(ServiceAlias $serviceAlias): JsonResponse
    {
        $this->authorize('delete', $serviceAlias);

        $serviceAlias->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service alias deleted successfully.',
        ]);
    }

    /**
     * @return array{total: int, rsm: int, nova: int, marvin: int}
     */
    private function getMetrics(): array
    {
        return [
            'total' => ServiceAlias::count(),
            'rsm' => ServiceAlias::where('source', SSAImportType::RSM->value)->count(),
            'nova' => ServiceAlias::where('source', SSAImportType::NOVA->value)->count(),
            'marvin' => ServiceAlias::where('source', SSAImportType::MARVIN->value)->count(),
        ];
    }
}
