<?php

use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;

it('creates user via command and sends welcome mail', function () {
    Mail::fake();

    $this->artisan('user:create-welcome', [
        'name' => 'Cmd User',
        'email' => 'cmd@example.com',
        '--password' => 'Secret123!',
        '--role' => 'therapist',
    ])->assertExitCode(0);

    $this->assertDatabaseHas('users', [
        'email' => 'cmd@example.com',
        'role' => 'therapist',
    ]);

    Mail::assertSent(WelcomeUserMail::class, function ($mailable) {
        return $mailable->email === 'cmd@example.com';
    });
});
