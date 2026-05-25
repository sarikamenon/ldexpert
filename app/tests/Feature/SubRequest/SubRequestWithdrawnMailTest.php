<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Events\ScheduleSubRequest\Withdrawn;
use App\Listeners\ScheduleSubRequest\SendWithdrawnNotification;
use App\Mail\ScheduleSubRequest\SubRequestWithdrawnMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

it('renders a withdrawal envelope and content for the covering therapist', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    $mail = new SubRequestWithdrawnMail($request->fresh(), $w['B']);

    $envelope = $mail->envelope();
    expect($envelope->subject)->toContain('was withdrawn');
    expect($envelope->subject)->toContain($w['A']->name);

    $content = $mail->content();
    expect($content->view)->toBe('emails.sub-request-withdrawn');
    expect($content->with['requesterName'])->toBe($w['A']->name);
    expect($content->with['coveringTherapist']->is($w['B']))->toBeTrue();
});

it('is queued so the email is sent off the request cycle', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    expect(new SubRequestWithdrawnMail($request->fresh(), $w['B']))
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('listener sends the withdrawal mail to the covering therapist', function () {
    Mail::fake();

    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);

    (new SendWithdrawnNotification)->handle(new Withdrawn($request->fresh(), $w['B']));

    // The mailable itself implements ShouldQueue, so ->send() still enqueues it.
    Mail::assertQueued(SubRequestWithdrawnMail::class, fn ($m) => $m->hasTo($w['B']->email));
});
