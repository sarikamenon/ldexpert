<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Student\Services\StudentImportService;
use App\Enums\StudentImportStatus;
use App\Mail\StudentImportCompletedMail;
use App\Models\StudentImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class ProcessStudentImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly StudentImport $import,
    ) {}

    public function handle(StudentImportService $importService): void
    {
        try {
            // Update import status to processing
            $this->import->update([
                'status' => StudentImportStatus::PROCESSING,
                'started_at' => now(),
            ]);

            // Process the import
            $importService->processImport($this->import);

            // Update import status to completed
            $this->import->update([
                'status' => StudentImportStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            // Send completion notification
            if ($this->import->user && $this->import->user->email) {
                Mail::to($this->import->user->email)->send(
                    new StudentImportCompletedMail($this->import)
                );
            }
        } catch (\Throwable $e) {
            Log::error('Student import processing failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update import status to failed
            $this->import->update([
                'status' => StudentImportStatus::FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Student import job failed', [
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        $this->import->update([
            'status' => StudentImportStatus::FAILED,
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
