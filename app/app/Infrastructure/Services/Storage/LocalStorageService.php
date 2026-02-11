<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Storage;

use App\Domain\Storage\Services\StorageServiceInterface;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LocalStorageService implements StorageServiceInterface
{
    public function put(string $path, string $contents): void
    {
        Storage::disk('local')->put($path, $contents);
    }

    public function get(string $path): string|false
    {
        return Storage::disk('local')->get($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk('local')->exists($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk('local')->delete($path);
    }

    public function download(string $path, string $name): StreamedResponse
    {
        return Storage::disk('local')->download($path, $name);
    }

    public function url(string $path): string
    {
        return Storage::disk('local')->url($path);
    }
}
