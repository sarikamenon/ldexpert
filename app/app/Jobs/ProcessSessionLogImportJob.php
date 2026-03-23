<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\SessionLog\Services\SessionLogImportService;
use App\Enums\SessionLogImportStatus;
use App\Models\SessionLogImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessSessionLogImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        private readonly SessionLogImport $import,
    ) {}

    public function handle(SessionLogImportService $importService): void
    {
        try {
            $this->import->update([
                'status' => SessionLogImportStatus::PROCESSING,
                'started_at' => now(),
            ]);

            $importService->processImport($this->import);

            $this->import->refresh();
            if ($this->import->status === SessionLogImportStatus::FAILED) {
                return;
            }

            $this->import->update([
                'status' => SessionLogImportStatus::COMPLETED,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Session log import processing failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->import->update([
                'status' => SessionLogImportStatus::FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Session log import job failed', [
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        $this->import->update([
            'status' => SessionLogImportStatus::FAILED,
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
