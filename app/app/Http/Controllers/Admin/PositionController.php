<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Position\Services\PositionCatalogService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\DTOs\ChangePositionStatusDTO;
use App\DTOs\CreatePositionDTO;
use App\DTOs\PositionFilterDTO;
use App\DTOs\UpdatePositionDTO;
use App\Enums\PositionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Position\ChangePositionStatusRequest;
use App\Http\Requests\Admin\Position\IndexPositionRequest;
use App\Http\Requests\Admin\Position\StorePositionRequest;
use App\Http\Requests\Admin\Position\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PositionController extends Controller
{
    public function __construct(
        private readonly PositionCatalogService $positionCatalog,
        private readonly ServiceCatalogService $serviceCatalog,
    ) {}

    public function index(IndexPositionRequest $request): View
    {
        $this->authorize('viewAny', Position::class);

        $filters = PositionFilterDTO::fromArray($request->validated());
        $positions = $this->positionCatalog->paginate($filters);
        $metrics = $this->positionCatalog->metrics();

        return view('admin.positions.index', [
            'positions' => $positions,
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'statuses' => PositionStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Position::class);

        $services = $this->serviceCatalog->listActiveForSelect();

        return view('admin.positions.create', [
            'services' => $services,
        ]);
    }

    public function store(StorePositionRequest $request): RedirectResponse
    {
        $this->authorize('create', Position::class);

        $dto = CreatePositionDTO::fromArray($request->validated());
        $this->positionCatalog->create($dto);

        return redirect()
            ->route('admin.positions.index')
            ->with('status', 'Position created successfully.');
    }

    public function edit(Position $position): View
    {
        $this->authorize('update', $position);

        $position->load('services');
        $services = $this->serviceCatalog->listActiveForSelect();

        return view('admin.positions.edit', [
            'position' => $position,
            'services' => $services,
        ]);
    }

    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $this->authorize('update', $position);

        $dto = UpdatePositionDTO::fromArray($request->validated());
        $this->positionCatalog->update($position, $dto);

        return redirect()
            ->route('admin.positions.index')
            ->with('status', 'Position updated successfully.');
    }

    public function updateStatus(ChangePositionStatusRequest $request, Position $position): JsonResponse
    {
        $this->authorize('changeStatus', $position);

        $dto = ChangePositionStatusDTO::fromArray($request->validated());
        $position = $this->positionCatalog->changeStatus($position, $dto);

        $message = $position->status === PositionStatus::ACTIVE
            ? 'Position activated successfully.'
            : 'Position deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function export(IndexPositionRequest $request): StreamedResponse
    {
        $this->authorize('export', Position::class);

        $filters = PositionFilterDTO::fromArray($request->validated());
        $positions = $this->positionCatalog->all($filters);
        $filename = sprintf('positions-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($positions): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Name',
                'Services',
                'Status',
            ]);

            /** @var Position $position */
            foreach ($positions as $position) {
                fputcsv($handle, [
                    $position->id,
                    $position->name,
                    $position->services->pluck('name')->implode(', '),
                    $position->status->value,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
