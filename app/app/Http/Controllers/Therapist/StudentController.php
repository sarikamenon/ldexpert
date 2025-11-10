<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\User\Services\UserService;
use App\DTOs\CreateUserDTO;
use App\DTOs\CreateStudentProfileDTO;
use App\DTOs\UpdateStudentProfileDTO;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\StoreStudentRequest;
use App\Http\Requests\Therapist\UpdateStudentRequest;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeUserMail;

class StudentController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StudentProfile::class);

        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $students = $user->students()->with('studentProfile')->get();

        return view('therapist.students.index', [
            'students' => $students,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StudentProfile::class);

        $parents = User::where('role', Role::PARENT->value)->get();

        return view('therapist.students.create', [
            'parents' => $parents,
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $this->authorize('create', StudentProfile::class);
        $plainPassword = Str::random(16);

        // Generate full name from name components
        $firstName = $request->filled('first_name') ? $request->string('first_name')->toString() : '';
        $middleName = $request->filled('middle_name') ? $request->string('middle_name')->toString() : '';
        $lastName = $request->filled('last_name') ? $request->string('last_name')->toString() : '';
        $fullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));

        // Create user DTO with full name
        $userDTO = CreateUserDTO::fromArray([
            'name' => $fullName ?: $request->string('email')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $plainPassword,
        ]);

        // Create profile DTO (user_id will be set by the service)
        $profileData = [
            'user_id' => 0, // Temporary, will be set by service
            'first_name' => $firstName ?: null,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName ?: null,
            'school' => $request->filled('school') ? $request->string('school')->toString() : null,
            'id_number' => $request->filled('id_number') ? $request->string('id_number')->toString() : null,
            'timezone' => $request->filled('timezone') ? $request->string('timezone')->toString() : null,
            'gender' => $request->filled('gender') ? $request->string('gender')->toString() : null,
            'address' => $request->filled('address') ? $request->string('address')->toString() : null,
            'city' => $request->filled('city') ? $request->string('city')->toString() : null,
            'state' => $request->filled('state') ? $request->string('state')->toString() : null,
            'zip_code' => $request->filled('zip_code') ? $request->string('zip_code')->toString() : null,
            'parent_guardian_name' => $request->filled('parent_guardian_name') ? $request->string('parent_guardian_name')->toString() : null,
            'parent_guardian_email' => $request->filled('parent_guardian_email') ? $request->string('parent_guardian_email')->toString() : null,
            'parent_guardian_phone' => $request->filled('parent_guardian_phone') ? $request->string('parent_guardian_phone')->toString() : null,
            'date_of_birth' => $request->filled('date_of_birth') ? $request->string('date_of_birth')->toString() : null,
            'grade_level' => $request->filled('grade_level') ? $request->string('grade_level')->toString() : null,
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
        ];

        $user = $this->userService->createWithProfile($userDTO, 'student', $profileData);

        // Send welcome email with the generated password
        Mail::to($user->email)->send(new WelcomeUserMail(
            name: $user->name,
            email: $user->email,
            plainPassword: $plainPassword
        ));

        // Assign student to current therapist
        $therapist = $request->user();
        if ($therapist) {
            $therapist->students()->attach($user->id, [
                'assigned_at' => now(),
                'status' => 'active',
            ]);
        }

        return redirect()->route('therapist.students.index')->with('status', 'Student created successfully.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user->studentProfile);

        return view('therapist.students.show', [
            'student' => $user->load('studentProfile'),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user->studentProfile);

        $parents = User::where('role', Role::PARENT->value)->get();

        return view('therapist.students.edit', [
            'student' => $user->load('studentProfile'),
            'parents' => $parents,
        ]);
    }

    public function update(UpdateStudentRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user->studentProfile);

        // Generate full name from name components
        $firstName = $request->filled('first_name') ? $request->string('first_name')->toString() : '';
        $middleName = $request->filled('middle_name') ? $request->string('middle_name')->toString() : '';
        $lastName = $request->filled('last_name') ? $request->string('last_name')->toString() : '';
        $fullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));

        // Update user basic info with full name
        $user->name = $fullName ?: $request->string('email')->toString();
        $user->email = $request->string('email')->toString();
        $user->save();

        // Create profile update DTO
        $profileDTO = UpdateStudentProfileDTO::fromArray([
            'first_name' => $firstName ?: null,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName ?: null,
            'school' => $request->input('school'),
            'id_number' => $request->input('id_number'),
            'timezone' => $request->input('timezone'),
            'gender' => $request->input('gender'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'zip_code' => $request->input('zip_code'),
            'parent_guardian_name' => $request->input('parent_guardian_name'),
            'parent_guardian_email' => $request->input('parent_guardian_email'),
            'parent_guardian_phone' => $request->input('parent_guardian_phone'),
            'date_of_birth' => $request->input('date_of_birth'),
            'grade_level' => $request->input('grade_level'),
            'parent_id' => $request->input('parent_id'),
        ]);

        // Update profile using DTO
        $user->studentProfile()->update($profileDTO->toArray());

        return redirect()->route('therapist.students.show', $user)->with('status', 'Student updated.');
    }

    public function activate(User $user): RedirectResponse
    {
        $this->authorize('update', $user->studentProfile);

        $user->status = UserStatus::ACTIVE;
        $user->save();

        return redirect()->route('therapist.students.index')->with('status', 'Student activated.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        $this->authorize('update', $user->studentProfile);

        $user->status = UserStatus::INACTIVE;
        $user->save();

        return redirect()->route('therapist.students.index')->with('status', 'Student deactivated.');
    }
}
