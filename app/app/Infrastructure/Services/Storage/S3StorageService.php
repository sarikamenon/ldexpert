<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Storage;

use App\Domain\Storage\Services\StorageServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckExistence;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class S3StorageService implements StorageServiceInterface
{
    public function put(string $path, string $contents): void
    {
        Storage::disk('s3')->put($path, $contents);
    }

    public function get(string $path): ?string
    {
        return Storage::disk('s3')->get($path);
    }

    public function exists(string $path): bool
    {
        try {
            return Storage::disk('s3')->exists($path);
        } catch (UnableToCheckExistence $exception) {
            Log::warning('Unable to check file existence on S3.', [
                'path' => $path,
                'disk' => 's3',
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    public function download(string $path, string $name): StreamedResponse
    {
        return Storage::disk('s3')->download($path, $name);
    }

    public function url(string $path): string
    {
        return Storage::disk('s3')->url($path);
    }
}
