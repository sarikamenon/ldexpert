<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Domain\Therapist\Services\TherapistService;
use App\Domain\User\Services\UserService;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\CreateTherapistDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\UpdateTherapistDTO;
use App\Enums\EmployeeType;
use App\Enums\Role;
use App\Enums\TherapistPosition;
use App\Enums\TherapistTitle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Therapist\ChangeTherapistStatusRequest;
use App\Http\Requests\Admin\Therapist\ExportTherapistsRequest;
use App\Http\Requests\Admin\Therapist\IndexTherapistRequest;
use App\Http\Requests\Admin\Therapist\StoreTherapistRequest;
use App\Http\Requests\Admin\Therapist\UpdateTherapistRequest;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Models\ServiceSupportAgreement;
use App\Enums\SSAStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TherapistController extends Controller
{
    public function __construct(
        private readonly TherapistService $therapistService,
        private readonly UserService $userService,
    ) {}

    public function index(IndexTherapistRequest $request): View
    {
        $this->authorize('viewAny', TherapistProfile::class);

        $filters = TherapistFilterDTO::fromRequest($request->validated());
        $therapists = $this->therapistService->list($filters);
        $metrics = $this->therapistService->getMetrics();

        return view('admin.therapists.index', [
            'therapists' => $therapists,
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'positions' => TherapistPosition::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TherapistProfile::class);

        return view('admin.therapists.create', $this->referenceData());
    }

    public function store(StoreTherapistRequest $request): RedirectResponse
    {
        $this->authorize('create', TherapistProfile::class);

        $validated = $request->validated();
        $validated['password'] = Str::password(12);

        $dto = CreateTherapistDTO::fromArray($validated);
        $this->therapistService->create($dto);

        return redirect()
            ->route('admin.therapists.index')
            ->with('status', 'Therapist added successfully.');
    }

    public function edit(User $therapist): View
    {
        $this->authorize('update', TherapistProfile::class);

        return view('admin.therapists.edit', [
            'therapist' => $therapist->load('therapistProfile'),
        ] + $this->referenceData());
    }

    public function show(User $therapist): View
    {
        $this->authorize('view', TherapistProfile::class);

        $therapist->load([
            'therapistProfile',
            'students.studentProfile.school',
        ]);

        $ssas = ServiceSupportAgreement::with(['student', 'primaryService'])
            ->where('assigned_therapist_id', $therapist->id)
            ->orderByDesc('start_date')
            ->get();

        $totalTho = (int) $ssas->sum('tho_minutes');
        $served = (int) $ssas->sum('served_minutes');

        $chartData = [
            'served' => $served,
            'remaining' => max(0, $totalTho - $served),
            'progress' => $totalTho > 0 ? round(($served / $totalTho) * 100, 1) : 0,
        ];

        $metrics = [
            'total_students' => $therapist->students->count(),
            'active_ssas' => $ssas->where('status', SSAStatus::ACTIVE)->count(),
            'completed_ssas' => $ssas->where('status', SSAStatus::COMPLETED)->count(),
            'pending_ssas' => $ssas->where('status', SSAStatus::PENDING)->count(),
        ];

        return view('admin.therapists.show', [
            'therapist' => $therapist,
            'ssas' => $ssas,
            'students' => $therapist->students,
            'chartData' => $chartData,
            'metrics' => $metrics,
        ]);
    }

    public function update(UpdateTherapistRequest $request, User $therapist): RedirectResponse
    {
        $this->authorize('update', TherapistProfile::class);

        $dto = UpdateTherapistDTO::fromArray($request->validated());
        $this->therapistService->update($therapist, $dto);

        return redirect()
            ->route('admin.therapists.index')
            ->with('status', 'Therapist information updated successfully.');
    }

    public function updateStatus(ChangeTherapistStatusRequest $request, User $therapist): JsonResponse
    {
        $this->authorize('changeStatus', TherapistProfile::class);

        $dto = ChangeTherapistStatusDTO::fromArray($request->validated());
        $this->therapistService->changeStatus($therapist, $dto);

        $message = $dto->status === 'active'
            ? 'Therapist activated successfully.'
            : 'Therapist deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function export(ExportTherapistsRequest $request): StreamedResponse
    {
        $this->authorize('export', TherapistProfile::class);

        $filters = TherapistFilterDTO::fromRequest($request->validated());
        $therapists = $this->therapistService->export($filters);
        $filename = sprintf('therapists-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($therapists): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Name',
                'Email',
                'Manager',
                'Phone',
                'Position',
                'Type',
                'Status',
            ]);

            foreach ($therapists as $therapist) {
                $profile = $therapist->therapistProfile;
                fputcsv($handle, [
                    $therapist->id,
                    $therapist->name,
                    $therapist->email,
                    $profile?->manager?->name ?? '—',
                    $profile?->phone ?? '—',
                    $profile?->position?->value ?? $profile?->position ?? '—',
                    $profile?->employee_type?->value ?? $profile?->employee_type ?? '—',
                    $therapist->status?->value ?? $therapist->status ?? 'inactive',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function referenceData(): array
    {
        return [
            'states' => UsStates::getStates(),
            'timezones' => UsTimezones::getTimezones(),
            'managers' => $this->userService->listByRole(Role::ADMIN),
            'titles' => TherapistTitle::cases(),
            'positions' => TherapistPosition::cases(),
            'employeeTypes' => EmployeeType::cases(),
        ];
    }
}
