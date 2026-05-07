<?php

declare(strict_types=1);

use App\Enums\ServiceFrequency;
use App\Models\ServiceSupportAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats summaryLine with weekly frequency', function () {
    $ssa = ServiceSupportAgreement::factory()->make([
        'minutes_per_session' => 30,
        'sessions_per_frequency' => 2,
        'frequency' => ServiceFrequency::WEEKLY,
    ]);

    expect($ssa->summaryLine())->toBe('30m × 2/wk');
});

it('formats summaryLine for bi-weekly, monthly, quarterly, and one-time frequencies', function () {
    $cases = [
        [ServiceFrequency::BI_WEEKLY, '45m × 1/2 wk'],
        [ServiceFrequency::MONTHLY, '45m × 1/mo'],
        [ServiceFrequency::QUARTERLY, '45m × 1/qtr'],
        [ServiceFrequency::ONE_TIME, '45m × 1/total'],
    ];

    foreach ($cases as [$frequency, $expected]) {
        $ssa = ServiceSupportAgreement::factory()->make([
            'minutes_per_session' => 45,
            'sessions_per_frequency' => 1,
            'frequency' => $frequency,
        ]);

        expect($ssa->summaryLine())->toBe($expected);
    }
});

it('returns null from summaryLine when frequency is missing', function () {
    $ssa = ServiceSupportAgreement::factory()->make([
        'minutes_per_session' => 30,
        'sessions_per_frequency' => 2,
        'frequency' => null,
    ]);

    expect($ssa->summaryLine())->toBeNull();
});

it('formats hoursLine using tho_hours and served_hours accessors', function () {
    $ssa = ServiceSupportAgreement::factory()->make([
        'tho_minutes' => 1440,
        'served_minutes' => 360,
    ]);

    expect($ssa->hoursLine())->toBe('THO 24h · Used 6h');
});

it('formats dateRangeFormatted with both dates', function () {
    $ssa = ServiceSupportAgreement::factory()->make([
        'start_date' => '2026-03-01',
        'end_date' => '2026-06-30',
    ]);

    expect($ssa->dateRangeFormatted())->toBe('Mar 01, 2026 → Jun 30, 2026');
});

it('formats dateRangeFormatted as ongoing when end_date is null', function () {
    $ssa = ServiceSupportAgreement::factory()->make([
        'start_date' => '2026-03-01',
        'end_date' => null,
    ]);

    expect($ssa->dateRangeFormatted())->toBe('Mar 01, 2026 → ongoing');
});
