<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\StudentImport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class StudentImportCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly StudentImport $import,
    ) {}

    public function build(): self
    {
        $import = $this->import->load('rows');

        $stats = [
            'total' => $import->total_rows,
            'success' => $import->rows()->where('status', 'done')->count(),
            'duplicates' => $import->rows()->where('status', 'duplicate')->count(),
            'errors' => $import->rows()->where('status', 'validation_error')->count(),
        ];

        return $this->subject('Student Import Completed')
            ->view('emails.student-import-completed', [
                'import' => $import,
                'stats' => $stats,
            ]);
    }
}
