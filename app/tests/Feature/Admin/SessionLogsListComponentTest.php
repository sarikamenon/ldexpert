<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

// Regression for DataTables "Requested unknown parameter '7'": the header
// column count must match the 7 cells returned by SessionLogRowTransformer
// and TherapistSessionLogRowTransformer, which both feed this component.

it('renders the 7-column header matching the row transformers', function () {
    $html = Blade::render(
        '<x-admin.session-logs-list :statuses="$statuses" :datatable-url="$url" context="detail" />',
        ['statuses' => [], 'url' => '/admin/session-logs/data'],
    );

    expect(substr_count($html, '<th>'))->toBe(7)
        ->and($html)->toContain('<th>Notes</th>')
        ->and($html)->not->toContain('Entry Info');
});
