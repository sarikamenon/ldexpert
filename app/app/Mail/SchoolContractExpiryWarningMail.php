<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\School;
use App\Models\SchoolContract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SchoolContractExpiryWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly School $school,
        public readonly SchoolContract $contract,
    ) {}

    public function build(): self
    {
        return $this->subject("Contract Expiring Soon: {$this->school->display_name}")
            ->view('emails.school-contract-expiry-warning', [
                'school' => $this->school,
                'contract' => $this->contract,
                'contractsUrl' => route('admin.schools.show', ['school' => $this->school->id, 'tab' => 'contracts']),
            ]);
    }
}
