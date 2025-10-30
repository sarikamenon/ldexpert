<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\User\Services\UserService;
use App\DTOs\CreateUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\StoreStudentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function create(): View
    {
        return view('therapist.students.create');
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $dto = CreateUserDTO::fromArray([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);
        $this->userService->createWithRole($dto, 'student');

        return redirect()->route('therapist.students.create')->with('status', 'Student created.');
    }
}
