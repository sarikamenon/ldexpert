<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class WelcomeStudentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $username,
        public readonly string $email,
        public readonly string $plainPassword,
    ) {}

    public function build(): self
    {
        return $this->subject('Welcome to NOVA')
            ->view('emails.welcome-student');
    }
}
