<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Mail\SubRequestInvitationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

it('renders an invitation envelope and content for the invitee', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], 'Out sick');

    $mail = new SubRequestInvitationMail($request->fresh(), $w['B']);

    $envelope = $mail->envelope();
    expect($envelope->subject)->toContain('Coverage request from');
    expect($envelope->subject)->toContain($w['A']->name);

    $content = $mail->content();
    expect($content->view)->toBe('emails.sub-request-invitation');
    expect($content->with['requesterName'])->toBe($w['A']->name);
    expect($content->with['reason'])->toBe('Out sick');
    expect($content->with['invitee']->is($w['B']))->toBeTrue();
});
