<?php

declare(strict_types=1);

namespace App\Domain\Contract\Services;

use App\Domain\Storage\Services\StorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class ContractDocumentService
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
    ) {}

    /**
     * Store a document and return the data array to persist.
     *
     * @return array<string, mixed>
     */
    public function store(UploadedFile $file): array
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        $originalName = $file->getClientOriginalName();
        $filename = "{$timestamp}_{$random}_{$originalName}";
        $path = "contract-documents/{$year}/{$month}/{$filename}";

        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw new RuntimeException('Cannot read uploaded file.');
        }

        $this->storageService->put($path, (string) file_get_contents($realPath));

        return [
            'document_path' => $path,
            'document_name' => $originalName,
            'document_mime_type' => $file->getMimeType(),
            'document_size' => $file->getSize(),
        ];
    }

    /**
     * Delete a document if it exists.
     */
    public function delete(string $path): void
    {
        if ($this->storageService->exists($path)) {
            $this->storageService->delete($path);
        }
    }

    /**
     * Resolve the document data for an update operation.
     * Returns an array to be merged into the update payload, or an empty array if no change.
     *
     * @return array<string, mixed>
     */
    public function resolveForUpdate(?string $existingPath, ?UploadedFile $newDocument, bool $removeDocument): array
    {
        if ($newDocument !== null) {
            if ($existingPath !== null) {
                $this->delete($existingPath);
            }

            return $this->store($newDocument);
        }

        if ($removeDocument) {
            if ($existingPath !== null) {
                $this->delete($existingPath);
            }

            return [
                'document_path' => null,
                'document_name' => null,
                'document_mime_type' => null,
                'document_size' => null,
            ];
        }

        return [];
    }

    public function exists(string $path): bool
    {
        return $this->storageService->exists($path);
    }

    public function download(string $path, string $name): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->storageService->download($path, $name);
    }
}
