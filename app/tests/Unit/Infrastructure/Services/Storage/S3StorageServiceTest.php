<?php

declare(strict_types=1);

use App\Infrastructure\Services\Storage\S3StorageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckExistence;

afterEach(function () {
    Mockery::close();
});

test('s3 storage exists returns false when existence check fails', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Unable to check file existence on S3.'
                && ($context['path'] ?? null) === 'path/to/file'
                && ($context['disk'] ?? null) === 's3'
                && ($context['exception'] ?? null) instanceof UnableToCheckExistence;
        });

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')
        ->once()
        ->with('path/to/file')
        ->andThrow(UnableToCheckExistence::forLocation('path/to/file'));

    Storage::shouldReceive('disk')
        ->with('s3')
        ->andReturn($filesystem);

    $service = new S3StorageService;

    expect($service->exists('path/to/file'))->toBeFalse();
});
