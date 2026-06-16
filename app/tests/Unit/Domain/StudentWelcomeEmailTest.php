<?php

declare(strict_types=1);

use App\Domain\Student\Services\StudentService;
use App\Infrastructure\Repositories\EloquentStudentRepository;
use App\Mail\WelcomeStudentMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->service = new StudentService(new EloquentStudentRepository);
});

it('sends a welcome email to a single student with a reset link', function () {
    $student = User::factory()->student()->create(['email' => 'kid@example.com']);

    $this->service->sendWelcomeEmail($student);

    Mail::assertSent(WelcomeStudentMail::class, function (WelcomeStudentMail $mail) use ($student) {
        return $mail->hasTo('kid@example.com')
            && $mail->username === $student->username
            && str_contains($mail->resetUrl, 'reset-password/')
            && str_contains($mail->resetUrl, 'username='.urlencode($student->username));
    });
});

it('throws when the user is not a student', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => $this->service->sendWelcomeEmail($admin))
        ->toThrow(InvalidArgumentException::class);

    Mail::assertNothingSent();
});

it('sends welcome emails in bulk and reports counts', function () {
    $students = User::factory()->student()->count(3)->create();

    $result = $this->service->sendWelcomeEmails($students->pluck('id')->all());

    expect($result)->toBe(['sent' => 3, 'failed' => 0]);
    Mail::assertSent(WelcomeStudentMail::class, 3);
});

it('skips non-existent ids without sending', function () {
    $student = User::factory()->student()->create();

    $result = $this->service->sendWelcomeEmails([$student->id, 999999]);

    expect($result['sent'])->toBe(1);
    Mail::assertSent(WelcomeStudentMail::class, 1);
});
