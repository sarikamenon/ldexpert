<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Storage;

use App\Domain\Storage\Services\StorageServiceInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StorageService implements StorageServiceInterface
{
    public function __construct(
        private readonly S3StorageService $s3StorageService,
        private readonly LocalStorageService $localStorageService,
    ) {}

    public function put(string $path, string $contents): void
    {
        $this->resolve()->put($path, $contents);
    }

    public function get(string $path): string|false
    {
        return $this->resolve()->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->resolve()->exists($path);
    }

    public function delete(string $path): bool
    {
        return $this->resolve()->delete($path);
    }

    public function download(string $path, string $name): StreamedResponse
    {
        return $this->resolve()->download($path, $name);
    }

    public function url(string $path): string
    {
        return $this->resolve()->url($path);
    }

    private function resolve(): StorageServiceInterface
    {
        return match (config('filesystems.default')) {
            'local' => $this->localStorageService,
            default => $this->s3StorageService,
        };
    }
}
