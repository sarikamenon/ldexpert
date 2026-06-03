<?php

declare(strict_types=1);

use App\DataTables\Transformers\SessionLogRowTransformer;
use App\Models\SessionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function transformSessionLog(SessionLog $log): array
{
    $log->load(['student', 'school', 'therapist', 'service']);

    return SessionLogRowTransformer::transform($log);
}

it('returns seven columns in the new merged order', function () {
    $row = transformSessionLog(SessionLog::factory()->create());

    expect($row)->toHaveCount(7);
});

it('merges both amounts into a single Amounts cell', function () {
    $log = SessionLog::factory()->create([
        'school_invoice_amount' => 60,
        'therapist_billable_amount' => 80,
    ]);

    $amountsCell = transformSessionLog($log)[3];

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

    $notesCell = transformSessionLog($log)[4];

    expect($notesCell)
        ->toContain('data-notes-cell')
        ->toContain('data-notes-text')
        ->toContain('data-notes-toggle')
        ->toContain('Child engaged well &amp; completed all tasks')
        ->toContain('Read more');
});

it('shows a dash in the notes cell when notes are empty', function () {
    $log = SessionLog::factory()->create(['notes' => '   ']);

    $notesCell = transformSessionLog($log)[4];

    expect($notesCell)
        ->toContain('-')
        ->not->toContain('data-notes-cell');
});
