<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Contract\Services\TherapistContractService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\ChangeContractStatusDTO;
use App\DTOs\CreateTherapistContractDTO;
use App\DTOs\TherapistContractFilterDTO;
use App\DTOs\UpdateTherapistContractDTO;
use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Exceptions\ContractOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contract\ChangeTherapistContractStatusRequest;
use App\Http\Requests\Admin\Contract\IndexTherapistContractRequest;
use App\Http\Requests\Admin\Contract\StoreTherapistContractRequest;
use App\Http\Requests\Admin\Contract\UpdateTherapistContractRequest;
use App\Models\TherapistContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class TherapistContractController extends Controller
{
    public function __construct(
        private readonly TherapistContractService $service,
        private readonly TherapistService $therapistService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(IndexTherapistContractRequest $request): View
    {
        $this->authorize('viewAny', TherapistContract::class);

        $filters = TherapistContractFilterDTO::fromArray($request->validated());
        $contracts = $this->service->paginate($filters);

        return view('admin.contracts.therapists.index', [
            'contracts' => $contracts,
            'metrics' => $this->service->metrics(),
            'filters' => $request->validated(),
            'statuses' => ContractStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TherapistContract::class);

        return view('admin.contracts.therapists.create', $this->formData());
    }

    public function store(StoreTherapistContractRequest $request): RedirectResponse
    {
        $this->authorize('create', TherapistContract::class);

        $dto = CreateTherapistContractDTO::fromArray($request->validated());

        try {
            $this->service->create($dto);
        } catch (ContractOverlapException $exception) {
            return back()
                ->withErrors(['start_date' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.contracts.therapists.index')
            ->with('status', 'Therapist contract created successfully.');
    }

    public function show(TherapistContract $therapistContract): View
    {
        $this->authorize('view', $therapistContract);

        return view('admin.contracts.therapists.show', [
            'contract' => $therapistContract->load(['therapist.user', 'services.service']),
        ]);
    }

    public function edit(TherapistContract $therapistContract): View
    {
        $this->authorize('update', $therapistContract);

        return view('admin.contracts.therapists.edit', [
            'contract' => $therapistContract->load(['therapist', 'services']),
        ] + $this->formData());
    }

    public function update(UpdateTherapistContractRequest $request, TherapistContract $therapistContract): RedirectResponse
    {
        $this->authorize('update', $therapistContract);

        $dto = UpdateTherapistContractDTO::fromArray($request->validated());

        try {
            $this->service->update($therapistContract, $dto);
        } catch (ContractOverlapException $exception) {
            return back()
                ->withErrors(['start_date' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.contracts.therapists.index')
            ->with('status', 'Therapist contract updated successfully.');
    }

    public function updateStatus(ChangeTherapistContractStatusRequest $request, TherapistContract $therapistContract): JsonResponse
    {
        $this->authorize('changeStatus', $therapistContract);

        $dto = ChangeContractStatusDTO::fromArray($request->validated());

        try {
            $contract = $this->service->changeStatus($therapistContract, $dto);
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
            'therapists' => $this->therapistService->listActiveProfilesForSelect(),
            'services' => $this->serviceCatalogService->listActiveForSelect(),
            'statuses' => ContractStatus::cases(),
            'rateTypes' => RateType::cases(),
        ];
    }
}
