<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupEmailLogStatus;
use App\Enums\ScheduleMakeupEmailLogType;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;

// ─── ScheduleMakeupRequestStatus ───────────────────────────────────────────

it('status labels cover all cases', function () {
    foreach (ScheduleMakeupRequestStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

it('terminal statuses are declined, scheduled, failed, not_required', function () {
    expect(ScheduleMakeupRequestStatus::DECLINED->isTerminal())->toBeTrue()
        ->and(ScheduleMakeupRequestStatus::SCHEDULED->isTerminal())->toBeTrue()
        ->and(ScheduleMakeupRequestStatus::FAILED->isTerminal())->toBeTrue()
        ->and(ScheduleMakeupRequestStatus::NOT_REQUIRED->isTerminal())->toBeTrue();
});

it('non-terminal statuses are pending, sent, requested', function () {
    expect(ScheduleMakeupRequestStatus::PENDING->isTerminal())->toBeFalse()
        ->and(ScheduleMakeupRequestStatus::SENT->isTerminal())->toBeFalse()
        ->and(ScheduleMakeupRequestStatus::REQUESTED->isTerminal())->toBeFalse();
});

it('values() returns every case value as a string array', function () {
    $values = ScheduleMakeupRequestStatus::values();

    expect($values)->toBeArray()->toHaveCount(count(ScheduleMakeupRequestStatus::cases()));

    foreach (ScheduleMakeupRequestStatus::cases() as $case) {
        expect($values)->toContain($case->value);
    }
});

it('status values round-trip through from()', function () {
    foreach (ScheduleMakeupRequestStatus::cases() as $case) {
        expect(ScheduleMakeupRequestStatus::from($case->value))->toBe($case);
    }
});

// ─── ScheduleMakeupRespondedByType ─────────────────────────────────────────

it('responded_by_type has parent, therapist, system cases', function () {
    expect(ScheduleMakeupRespondedByType::PARENT->value)->toBe('parent')
        ->and(ScheduleMakeupRespondedByType::THERAPIST->value)->toBe('therapist')
        ->and(ScheduleMakeupRespondedByType::SYSTEM->value)->toBe('system');
});

it('responded_by_type values round-trip through from()', function () {
    foreach (ScheduleMakeupRespondedByType::cases() as $case) {
        expect(ScheduleMakeupRespondedByType::from($case->value))->toBe($case);
    }
});

// ─── ScheduleMakeupResponseSource ──────────────────────────────────────────

it('response_source has email_link, therapist_manual, auto_declined cases', function () {
    expect(ScheduleMakeupResponseSource::EMAIL_LINK->value)->toBe('email_link')
        ->and(ScheduleMakeupResponseSource::THERAPIST_MANUAL->value)->toBe('therapist_manual')
        ->and(ScheduleMakeupResponseSource::AUTO_DECLINED->value)->toBe('auto_declined');
});

it('response_source values round-trip through from()', function () {
    foreach (ScheduleMakeupResponseSource::cases() as $case) {
        expect(ScheduleMakeupResponseSource::from($case->value))->toBe($case);
    }
});

// ─── ScheduleMakeupEmailLogStatus ──────────────────────────────────────────

it('email log status has queued, sent, failed cases', function () {
    expect(ScheduleMakeupEmailLogStatus::QUEUED->value)->toBe('queued')
        ->and(ScheduleMakeupEmailLogStatus::SENT->value)->toBe('sent')
        ->and(ScheduleMakeupEmailLogStatus::FAILED->value)->toBe('failed');
});

// ─── ScheduleMakeupEmailLogType ────────────────────────────────────────────

it('email log type includes reminder and all 5 therapist notification types', function () {
    expect(ScheduleMakeupEmailLogType::REMINDER->value)->toBe('reminder');

    $values = array_map(fn ($c) => $c->value, ScheduleMakeupEmailLogType::cases());

    expect($values)->toContain('therapist_availability_reminder')
        ->and($values)->toContain('therapist_no_availability_accepted')
        ->and($values)->toContain('therapist_declined')
        ->and($values)->toContain('therapist_makeup_scheduled')
        ->and($values)->toContain('therapist_non_accepted');
});
