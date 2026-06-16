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
        public readonly string $resetUrl,
    ) {}

    public function build(): self
    {
        return $this->subject('Welcome to '.(string) config('brand.name'))
            ->view('emails.welcome-student')
            ->with([
                'brandName' => (string) config('brand.short_name'),
                'platformName' => (string) config('brand.platform_name'),
                'supportEmail' => (string) config('brand.support_email'),
                'copyrightName' => (string) config('brand.copyright_name'),
                'currentYear' => now()->year,
            ]);
    }
}
