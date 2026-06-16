<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\CreateStudentDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\UpdateStudentDTO;
use App\Mail\WelcomeStudentMail;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

final class StudentService
{
    public function __construct(
        private readonly StudentRepositoryInterface $repository,
    ) {}

    public function create(CreateStudentDTO $dto): StudentProfile
    {
        $userData = $dto->toUserArray();
        $userData['password'] = Hash::make($dto->password);

        $profileData = $dto->toProfileArray(0);

        $school = $dto->schoolId !== null ? School::find($dto->schoolId) : null;

        if (empty($profileData['id_number']) && $school?->is_private_student) {
            $profileData['id_number'] = $this->generateUniqueStudentId();
        }

        $profile = $this->repository->create(
            $userData,
            $profileData // user_id will be set in repository
        );

        return $profile;
    }

    /**
     * Send the welcome email (username + set-password link) to a single student.
     *
     * Sending is the primary intent here, so failures are logged and re-thrown
     * for the caller to surface a friendly error.
     */
    public function sendWelcomeEmail(User $student): void
    {
        if (! $student->isStudent()) {
            throw new \InvalidArgumentException("User {$student->id} is not a student.");
        }

        $resetUrl = $this->buildPasswordResetUrl($student);

        try {
            Mail::to($student->email)->send(
                new WelcomeStudentMail(
                    name: $student->name,
                    username: $student->username,
                    email: $student->email,
                    resetUrl: $resetUrl,
                )
            );
        } catch (\Throwable $e) {
            Log::error('StudentService: failed to send welcome email', [
                'user_id' => $student->id,
                'email' => $student->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send the welcome email to many students, reporting partial success.
     *
     * @param  array<int, int>  $studentIds
     * @return array{sent: int, failed: int}
     */
    public function sendWelcomeEmails(array $studentIds): array
    {
        $students = User::query()->whereIn('id', $studentIds)->get();

        $sent = 0;
        $failed = 0;

        foreach ($students as $student) {
            try {
                $this->sendWelcomeEmail($student);
                $sent++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Build a username-based password reset URL, matching the app's
     * ResetPassword::createUrlUsing convention (see AppServiceProvider).
     */
    private function buildPasswordResetUrl(User $student): string
    {
        $token = Password::broker()->createToken($student);

        return url(route('password.reset', [
            'token' => $token,
            'username' => $student->getEmailForPasswordReset(),
        ], false));
    }

    public function update(User $user, UpdateStudentDTO $dto): StudentProfile
    {
        return $this->repository->update(
            $user,
            $dto->toUserArray(),
            $dto->toProfileArray()
        );
    }

    public function changeStatus(User $user, ChangeStudentStatusDTO $dto): User
    {
        return $this->repository->changeStatus($user, $dto);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function list(StudentFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, User>}
     */
    public function listForDataTables(StudentFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, User>}
     */
    public function listForDataTablesByTherapist(int $therapistId, array $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTablesByTherapist($therapistId, $filters, $params);
    }

    /** @return array<string, int> */
    public function getMetrics(?string $status = null): array
    {
        return $this->repository->getMetrics($status);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function listByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->listByTherapist($therapistId, $search, $status, $perPage);
    }

    public function countStudentsBySchool(int $schoolId): int
    {
        return $this->repository->countStudentsBySchool($schoolId);
    }

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsBySchool(int $schoolId): Collection
    {
        return $this->repository->listActiveStudentsBySchool($schoolId);
    }

    /**
     * @return Collection<int, User>
     */
    public function export(StudentFilterDTO $filters): Collection
    {
        return $this->repository->export($filters);
    }

    public function find(int $id): ?StudentProfile
    {
        return $this->repository->find($id);
    }

    public function countStudentsByTherapist(int $therapistId): int
    {
        return $this->repository->countStudentsByTherapist($therapistId);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function listStudentsByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->listStudentsByTherapist($therapistId, $search, $status, $perPage);
    }

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsByTherapist(int $therapistId): Collection
    {
        return $this->repository->listActiveStudentsByTherapist($therapistId);
    }

    public function getSchoolIdByUserId(int $userId): ?int
    {
        return $this->repository->getSchoolIdByUserId($userId);
    }

    private function generateUniqueStudentId(): int
    {
        do {
            $idNumber = random_int(10000000, 99999999);
        } while (StudentProfile::where('id_number', $idNumber)->exists());

        return $idNumber;
    }
}
