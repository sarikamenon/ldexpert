<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\School;
use App\Models\SchoolContract;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SchoolContractAutoExtendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly School $school,
        public readonly SchoolContract $contract,
        public readonly Carbon $oldEndDate,
        public readonly int $ssasExtended,
    ) {}

    public function build(): self
    {
        return $this->subject("Contract Auto-Extended: {$this->school->display_name}")
            ->view('emails.school-contract-auto-extended', [
                'school' => $this->school,
                'contract' => $this->contract,
                'oldEndDate' => $this->oldEndDate,
                'ssasExtended' => $this->ssasExtended,
                'contractsUrl' => route('admin.schools.show', ['school' => $this->school->id, 'tab' => 'contracts']),
            ]);
    }
}
