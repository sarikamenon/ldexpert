<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Storage\Services\StorageServiceInterface;
use App\DTOs\CreateStudentDocumentDTO;
use App\DTOs\StudentDocumentFilterDTO;
use App\Models\SessionLog;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class StudentDocumentService
{
    public function __construct(
        private readonly StudentDocumentRepositoryInterface $repository,
        private readonly StorageServiceInterface $storageService,
    ) {}

    public function create(CreateStudentDocumentDTO $dto): StudentDocument
    {
        // Validate documentable model exists
        $documentable = match ($dto->documentableType) {
            User::class => User::findOrFail($dto->documentableId),
            SessionLog::class => SessionLog::findOrFail($dto->documentableId),
            default => throw new \InvalidArgumentException('Invalid documentable type'),
        };

        // Upload file to configured storage service
        $filePath = $this->storeFile($dto->file);

        // Create document record
        $data = array_merge($dto->toArray(), [
            'file_name' => $dto->file->getClientOriginalName(),
            'file_path' => $filePath,
            'mime_type' => $dto->file->getMimeType(),
            'file_size' => $dto->file->getSize(),
        ]);

        return $this->repository->create($data);
    }

    public function list(StudentDocumentFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function listByStudent(int $studentId): Collection
    {
        return $this->repository->listByStudent($studentId);
    }

    public function listBySessionLog(int $sessionLogId): Collection
    {
        return $this->repository->listBySessionLog($sessionLogId);
    }

    public function download(StudentDocument $document): StreamedResponse
    {
        $exists = $this->storageService->exists($document->file_path);

        if (! $exists) {
            abort(404, 'File not found');
        }

        return $this->storageService->download($document->file_path, $document->file_name);
    }

    public function delete(StudentDocument $document): bool
    {
        // Delete file from storage
        if ($this->storageService->exists($document->file_path)) {
            $this->storageService->delete($document->file_path);
        }

        // Soft delete document record
        return $this->repository->delete($document);
    }

    private function storeFile(\Illuminate\Http\UploadedFile $file): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        $originalName = $file->getClientOriginalName();
        $filename = "{$timestamp}_{$random}_{$originalName}";

        $path = "student-documents/{$year}/{$month}/{$filename}";

        $this->storageService->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }
}
