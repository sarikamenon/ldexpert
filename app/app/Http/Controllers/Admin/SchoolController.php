<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Domain\School\Services\SchoolService;
use App\Domain\User\Services\UserService;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\Role;
use App\Enums\SchoolType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\School\ChangeSchoolStatusRequest;
use App\Http\Requests\Admin\School\ExportSchoolsRequest;
use App\Http\Requests\Admin\School\IndexSchoolRequest;
use App\Http\Requests\Admin\School\SchoolFormRequest;
use App\Http\Requests\Admin\School\StoreSchoolRequest;
use App\Http\Requests\Admin\School\UpdateSchoolRequest;
use App\Models\School;
use App\Models\User;
use App\Models\ServiceSupportAgreement;
use App\Enums\SSAStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SchoolController extends Controller
{
    public function __construct(
        private readonly SchoolService $schoolService,
        private readonly UserService $userService,
    ) {}

    public function index(IndexSchoolRequest $request): View
    {
        $this->authorize('viewAny', School::class);

        $filtersPayload = array_merge(
            $request->validated(),
            ['show_deactivated' => $request->boolean('show_deactivated')]
        );
        $filters = SchoolFilterDTO::fromArray($filtersPayload);
        $perPage = $request->integer('per_page', 25);

        return view('admin.schools.index', [
            'schools' => $this->schoolService->listSchools($filters, $perPage),
            'metrics' => $this->schoolService->summaryMetrics(),
            'filters' => $filtersPayload,
        ] + $this->referenceData());
    }

    public function create(): View
    {
        $this->authorize('create', School::class);

        return view('admin.schools.create', $this->referenceData());
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $this->authorize('create', School::class);

        $dto = CreateSchoolDTO::fromArray($this->formPayload($request));
        $this->schoolService->createSchool($dto);

        return redirect()
            ->route('admin.schools.index')
            ->with('status', 'School added successfully.');
    }

    public function edit(School $school): View
    {
        $this->authorize('update', $school);

        return view('admin.schools.edit', [
            'school' => $school,
        ] + $this->referenceData());
    }

    public function update(UpdateSchoolRequest $request, School $school): RedirectResponse
    {
        $this->authorize('update', $school);

        $dto = UpdateSchoolDTO::fromArray($this->formPayload($request));
        $this->schoolService->updateSchool($school, $dto);

        return redirect()
            ->route('admin.schools.index')
            ->with('status', 'School information updated successfully.');
    }

    public function updateStatus(ChangeSchoolStatusRequest $request, School $school): RedirectResponse
    {
        $this->authorize('changeStatus', $school);

        $dto = ChangeSchoolStatusDTO::fromArray($request->validated());
        $this->schoolService->changeStatus($school, $dto);

        $message = $dto->status->value === 'active'
            ? 'School activated successfully.'
            : 'School deactivated successfully.';

        return redirect()
            ->route('admin.schools.index')
            ->with('status', $message);
    }

    public function show(School $school): View
    {
        $this->authorize('view', $school);

        $school->load('manager');

        $students = User::query()
            ->where('role', Role::STUDENT)
            ->whereHas('studentProfile', fn($q) => $q->where('school_id', $school->id))
            ->with('studentProfile')
            ->get();

        $ssas = ServiceSupportAgreement::with(['student', 'primaryService', 'assignedTherapist'])
            ->whereHas('student.studentProfile', fn($q) => $q->where('school_id', $school->id))
            ->orderByDesc('start_date')
            ->get();

        $therapists = User::query()
            ->where('role', Role::THERAPIST)
            ->whereHas('students.studentProfile', fn($q) => $q->where('school_id', $school->id))
            ->with('therapistProfile')
            ->get()
            ->unique('id')
            ->values();

        $statusCounts = [
            'Active' => $ssas->where('status', SSAStatus::ACTIVE)->count(),
            'Pending' => $ssas->where('status', SSAStatus::PENDING)->count(),
            'Completed' => $ssas->where('status', SSAStatus::COMPLETED)->count(),
            'Deactivated' => $ssas->where('status', SSAStatus::DEACTIVATED)->count(),
        ];

        $metrics = [
            'total_students' => $students->count(),
            'total_therapists' => $therapists->count(),
            'total_ssas' => $ssas->count(),
        ];

        $chartData = [
            'labels' => array_keys($statusCounts),
            'data' => array_values($statusCounts),
        ];

        return view('admin.schools.show', [
            'school' => $school,
            'students' => $students,
            'therapists' => $therapists,
            'ssas' => $ssas,
            'statusCounts' => $statusCounts,
            'metrics' => $metrics,
            'chartData' => $chartData,
        ]);
    }

    public function export(ExportSchoolsRequest $request): StreamedResponse
    {
        $this->authorize('export', School::class);

        $filters = SchoolFilterDTO::fromArray(array_merge(
            $request->validated(),
            ['show_deactivated' => $request->boolean('show_deactivated')]
        ));
        $rows = $this->schoolService->exportSchools($filters);
        $filename = sprintf('schools-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Full Name',
                'Display Name',
                'Manager',
                'State',
                'Email',
                'Timezone',
                'Status',
            ]);

            /** @var \App\Models\School $school */
            foreach ($rows as $school) {
                fputcsv($handle, [
                    $school->id,
                    $school->full_name,
                    $school->display_name,
                    $school->manager?->name,
                    $school->state,
                    $school->contact_email,
                    $school->timezone,
                    $school->status?->value ?? $school->status,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function formPayload(SchoolFormRequest $request): array
    {
        $validated = $request->validated();
        $validated['is_private_student'] = $request->boolean('is_private_student');
        $validated['non_billable_scheduling'] = $request->boolean('non_billable_scheduling');

        return $validated;
    }

    private function referenceData(): array
    {
        return [
            'states' => UsStates::getStates(),
            'timezones' => UsTimezones::getTimezones(),
            'managers' => $this->userService->listByRole(Role::ADMIN),
            'schoolTypes' => SchoolType::values(),
        ];
    }
}
