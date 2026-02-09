<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\DTOs\StudentDocumentFilterDTO;
use App\Models\SessionLog;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentStudentDocumentRepository implements StudentDocumentRepositoryInterface
{
    public function create(array $data): StudentDocument
    {
        return StudentDocument::create($data);
    }

    public function find(int $id): ?StudentDocument
    {
        return StudentDocument::with(['uploadedBy', 'documentable'])->find($id);
    }

    public function list(StudentDocumentFilterDTO $filters): LengthAwarePaginator
    {
        $query = StudentDocument::query()
            ->with(['uploadedBy', 'documentable']);

        // Filter by student (documents attached to student or session logs for that student)
        if ($filters->studentId !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where(function ($subQ) use ($filters) {
                    $subQ->where('documentable_type', User::class)
                        ->where('documentable_id', $filters->studentId);
                })->orWhereHasMorph('documentable', [SessionLog::class], function ($subQ) use ($filters) {
                    $subQ->where('student_id', $filters->studentId);
                });
            });
        }

        // Filter by document type
        if ($filters->documentType !== null) {
            $query->where('document_type', $filters->documentType);
        }

        // Filter by uploaded by
        if ($filters->uploadedById !== null) {
            $query->where('uploaded_by_id', $filters->uploadedById);
        }

        // Search filter
        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('file_name', 'like', '%'.$filters->search.'%')
                    ->orWhere('description', 'like', '%'.$filters->search.'%');
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    public function listByStudent(int $studentId): Collection
    {
        return StudentDocument::query()
            ->where(function ($q) use ($studentId) {
                $q->where(function ($subQ) use ($studentId) {
                    $subQ->where('documentable_type', User::class)
                        ->where('documentable_id', $studentId);
                })->orWhereHasMorph('documentable', [SessionLog::class], function ($subQ) use ($studentId) {
                    $subQ->where('student_id', $studentId);
                });
            })
            ->with(['uploadedBy', 'documentable'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function listBySessionLog(int $sessionLogId): Collection
    {
        return StudentDocument::query()
            ->where('documentable_type', SessionLog::class)
            ->where('documentable_id', $sessionLogId)
            ->with(['uploadedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function delete(StudentDocument $document): bool
    {
        return $document->delete();
    }
}
