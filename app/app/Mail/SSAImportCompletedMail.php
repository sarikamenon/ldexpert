<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SSAImport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SSAImportCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SSAImport $import,
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

        return $this->subject('SSA Import Completed')
            ->view('emails.ssa-import-completed', [
                'import' => $import,
                'stats' => $stats,
            ]);
    }
}
