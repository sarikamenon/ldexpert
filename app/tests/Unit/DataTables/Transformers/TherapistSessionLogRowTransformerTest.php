<?php

declare(strict_types=1);

use App\DataTables\Transformers\TherapistSessionLogRowTransformer;
use App\Models\SessionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<int, string>
 */
function transformTherapistSessionLog(SessionLog $log, string $timezone = 'UTC'): array
{
    $log->load(['student', 'school', 'therapist', 'service']);

    return TherapistSessionLogRowTransformer::transform($log, $timezone);
}

it('returns seven columns in the merged order matching the admin layout', function () {
    $row = transformTherapistSessionLog(SessionLog::factory()->create());

    expect($row)->toHaveCount(7);
});

it('keeps the session time range in the merged date cell', function () {
    $log = SessionLog::factory()->create();

    $dateCell = transformTherapistSessionLog($log)[0];

    expect($dateCell)->toContain(' - ')
        ->and($dateCell)->toContain('Entry:');
});

it('derives the displayed date from the viewer timezone across the UTC day boundary', function (string $startUtc, string $endUtc, string $timezone, string $expectedDate) {
    $log = SessionLog::factory()->create([
        'session_date' => '2025-06-10',
        'start_time' => $startUtc,
        'end_time' => $endUtc,
    ]);

    $dateCell = transformTherapistSessionLog($log, $timezone)[0];

    expect($dateCell)->toContain($expectedDate);
})->with([
    'east of UTC rolls forward' => ['2025-06-10 19:00:00', '2025-06-10 19:30:00', 'Asia/Kolkata', 'Jun 11, 2025'],
    'west of UTC rolls back' => ['2025-06-10 03:00:00', '2025-06-10 03:30:00', 'America/Los_Angeles', 'Jun 09, 2025'],
]);

it('merges both amounts into a single Amounts cell', function () {
    $log = SessionLog::factory()->create([
        'school_invoice_amount' => 60,
        'therapist_billable_amount' => 80,
    ]);

    $amountsCell = transformTherapistSessionLog($log)[3];

    expect($amountsCell)
        ->toContain('School:')
        ->toContain('$60.00')
        ->toContain('Therapist:')
        ->toContain('$80.00');
});

it('renders the escaped notes preview with a read-more toggle', function () {
    $log = SessionLog::factory()->create([
        'notes' => 'Child engaged well & completed all tasks',
    ]);

    $notesCell = transformTherapistSessionLog($log)[4];

    expect($notesCell)
        ->toContain('data-notes-cell')
        ->toContain('data-notes-text')
        ->toContain('data-notes-toggle')
        ->toContain('Child engaged well &amp; completed all tasks')
        ->toContain('Read more');
});

it('shows a dash in the notes cell when notes are empty', function () {
    $log = SessionLog::factory()->create(['notes' => '   ']);

    $notesCell = transformTherapistSessionLog($log)[4];

    expect($notesCell)
        ->toContain('-')
        ->not->toContain('data-notes-cell');
});
