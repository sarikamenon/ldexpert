<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notYetInvoiced returns only schedules with a null invoice_id', function () {
    $invoice = Invoice::factory()->create();

    $unbilled = Schedule::factory()->create(['invoice_id' => null]);
    Schedule::factory()->create(['invoice_id' => $invoice->id]);

    $results = Schedule::query()->notYetInvoiced()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($unbilled->id);
});

test('forInvoice returns only schedules stamped with the given invoice', function () {
    $invoiceA = Invoice::factory()->create();
    $invoiceB = Invoice::factory()->create();

    $onA = Schedule::factory()->create(['invoice_id' => $invoiceA->id]);
    Schedule::factory()->create(['invoice_id' => $invoiceB->id]);
    Schedule::factory()->create(['invoice_id' => null]);

    $results = Schedule::query()->forInvoice($invoiceA->id)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($onA->id);
});

test('invoice relation resolves the stamped invoice', function () {
    $invoice = Invoice::factory()->create();
    $schedule = Schedule::factory()->create(['invoice_id' => $invoice->id]);

    expect($schedule->invoice?->id)->toBe($invoice->id);
});

test('invoice schedules relation returns its stamped schedules', function () {
    $invoice = Invoice::factory()->create();
    Schedule::factory()->create(['invoice_id' => $invoice->id]);
    Schedule::factory()->create(['invoice_id' => null]);

    expect($invoice->schedules()->count())->toBe(1);
});
