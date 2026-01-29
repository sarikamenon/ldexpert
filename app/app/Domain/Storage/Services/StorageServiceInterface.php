<?php

declare(strict_types=1);

namespace App\Domain\Storage\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface StorageServiceInterface
{
    public function put(string $path, string $contents): void;

    public function get(string $path): string|false;

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    public function download(string $path, string $name): StreamedResponse;

    public function url(string $path): string;
}
