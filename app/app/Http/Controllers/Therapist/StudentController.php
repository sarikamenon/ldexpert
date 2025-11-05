<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\User\Services\UserService;
use App\DTOs\CreateUserDTO;
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

        $students = $user->students()->with('studentProfile')->paginate(15);

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

        $dto = CreateUserDTO::fromArray([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $plainPassword,
        ]);

        $profileData = [
            'date_of_birth' => $request->filled('date_of_birth') ? $request->string('date_of_birth')->toString() : null,
            'grade_level' => $request->filled('grade_level') ? $request->string('grade_level')->toString() : null,
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
            'emergency_contact' => $request->filled('emergency_contact') ? $request->string('emergency_contact')->toString() : null,
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
        ];

        $user = $this->userService->createWithProfile($dto, 'student', $profileData);

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

        $user->name = $request->string('name')->toString();
        $user->email = $request->string('email')->toString();
        $user->save();

        $user->studentProfile()->update([
            'date_of_birth' => $request->input('date_of_birth'),
            'grade_level' => $request->input('grade_level'),
            'phone' => $request->input('phone'),
            'emergency_contact' => $request->input('emergency_contact'),
            'parent_id' => $request->input('parent_id'),
        ]);

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
