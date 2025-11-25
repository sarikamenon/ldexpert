<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Domain\Therapist\Services\TherapistService;
use App\Domain\Student\Services\StudentService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\User\Services\UserService;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\CreateTherapistDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\UpdateTherapistDTO;
use App\Enums\EmployeeType;
use App\Enums\Role;
use App\Enums\TherapistPosition;
use App\Enums\TherapistTitle;
use App\Enums\UserStatus;
use App\Enums\SSAStatus;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Therapist\ChangeTherapistStatusRequest;
use App\Http\Requests\Admin\Therapist\ExportTherapistsRequest;
use App\Http\Requests\Admin\Therapist\IndexTherapistRequest;
use App\Http\Requests\Admin\Therapist\StoreTherapistRequest;
use App\Http\Requests\Admin\Therapist\UpdateTherapistRequest;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Models\ServiceSupportAgreement;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TherapistController extends Controller
{
    public function __construct(
        private readonly TherapistService $therapistService,
        private readonly UserService $userService,
        private readonly StudentService $studentService,
        private readonly SSAService $ssaService,
        private readonly SchoolRepositoryInterface $schoolRepository,
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

    public function show(Request $request, User $therapist): View
    {
        $this->authorize('view', TherapistProfile::class);

        $therapist->load('therapistProfile');
        $activeTab = $request->query('tab', 'dashboard');

        $viewData = [
            'therapist' => $therapist,
            'activeTab' => $activeTab,
        ];

        // Load dashboard data (always needed for metrics)
        if ($activeTab === 'dashboard' || $activeTab === 'overview') {
            $ssasForMetrics = ServiceSupportAgreement::with(['student', 'primaryService'])
                ->where('assigned_therapist_id', $therapist->id)
                ->get();

            $totalTho = (int) $ssasForMetrics->sum('tho_minutes');
            $served = (int) $ssasForMetrics->sum('served_minutes');

            $viewData['chartData'] = [
                'served' => $served,
                'remaining' => max(0, $totalTho - $served),
                'progress' => $totalTho > 0 ? round(($served / $totalTho) * 100, 1) : 0,
            ];

            $studentsCount = User::query()
                ->where('role', Role::STUDENT)
                ->whereHas('therapists', fn($q) => $q->where('therapist_id', $therapist->id))
                ->count();

            $viewData['metrics'] = [
                'total_students' => $studentsCount,
                'active_ssas' => $ssasForMetrics->where('status', SSAStatus::ACTIVE)->count(),
                'completed_ssas' => $ssasForMetrics->where('status', SSAStatus::COMPLETED)->count(),
                'pending_ssas' => $ssasForMetrics->where('status', SSAStatus::PENDING)->count(),
            ];
        }

        // Load tab-specific data only when needed
        if ($activeTab === 'students') {
            // Filter students by therapist relationship
            $studentsQuery = User::query()
                ->where('role', Role::STUDENT)
                ->whereHas('therapists', fn($q) => $q->where('therapist_id', $therapist->id))
                ->with('studentProfile.school');

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->query('search');
                $studentsQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Apply status filter
            if ($request->filled('status')) {
                $studentsQuery->where('status', $request->query('status'));
            }

            $viewData['students'] = $studentsQuery->paginate($request->integer('per_page', 15));
            $viewData['studentFilters'] = $request->query();
            $viewData['schools'] = $this->schoolRepository->listAllForSelect();
            $viewData['statuses'] = UserStatus::cases();
        } elseif ($activeTab === 'ssas') {
            $filters = SSAFilterDTO::fromArray(
                array_merge($request->query(), ['therapist_id' => $therapist->id])
            );
            $viewData['ssas'] = $this->ssaService->paginate($filters);
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
            $viewData['students'] = User::query()
                ->where('role', Role::STUDENT)
                ->where('status', UserStatus::ACTIVE)
                ->whereHas('therapists', fn($q) => $q->where('therapist_id', $therapist->id))
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
            $viewData['therapists'] = User::query()
                ->where('role', Role::THERAPIST)
                ->where('status', UserStatus::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
            $viewData['services'] = Service::query()
                ->where('status', ServiceStatus::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name', 'is_frequency_service']);
        }

        return view('admin.therapists.show', $viewData);
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
