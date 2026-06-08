<?php

declare(strict_types=1);

use App\Domain\Billing\Services\BillingStartDateResolver;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->resolver = new BillingStartDateResolver;
});

test('therapist created on the 1st anchors to the 1st of the current month', function () {
    $start = $this->resolver->forTherapist(Carbon::parse('2026-06-01'));

    expect($start->toDateString())->toBe('2026-06-01');
});

test('therapist created on the 15th anchors to the 1st of the current month', function () {
    $start = $this->resolver->forTherapist(Carbon::parse('2026-06-15'));

    expect($start->toDateString())->toBe('2026-06-01');
});

test('therapist created on the 16th anchors to the 16th of the current month', function () {
    $start = $this->resolver->forTherapist(Carbon::parse('2026-06-16'));

    expect($start->toDateString())->toBe('2026-06-16');
});

test('therapist created on the last day of the month anchors to the 16th', function () {
    $start = $this->resolver->forTherapist(Carbon::parse('2026-06-30'));

    expect($start->toDateString())->toBe('2026-06-16');
});

test('non-private school anchors to the 1st of the current month', function () {
    $start = $this->resolver->forSchool(false, Carbon::parse('2026-06-20'));

    expect($start->toDateString())->toBe('2026-06-01');
});

test('private school anchors to the 1st of next month', function () {
    $start = $this->resolver->forSchool(true, Carbon::parse('2026-06-20'));

    expect($start->toDateString())->toBe('2026-07-01');
});

test('private school created in December anchors to the 1st of January next year', function () {
    $start = $this->resolver->forSchool(true, Carbon::parse('2026-12-10'));

    expect($start->toDateString())->toBe('2027-01-01');
});
