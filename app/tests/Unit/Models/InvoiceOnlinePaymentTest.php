<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('allowsOnlinePayment is true for private-student schools', function () {
    $school = School::factory()->create(['is_private_student' => true]);
    $invoice = Invoice::factory()->create(['school_id' => $school->id]);

    expect($invoice->allowsOnlinePayment())->toBeTrue();
});

test('allowsOnlinePayment is false for non-private schools', function () {
    $school = School::factory()->create(['is_private_student' => false]);
    $invoice = Invoice::factory()->create(['school_id' => $school->id]);

    expect($invoice->allowsOnlinePayment())->toBeFalse();
});

test('allowsOnlinePayment is false when school is missing', function () {
    $invoice = Invoice::factory()->make(['school_id' => null]);

    expect($invoice->allowsOnlinePayment())->toBeFalse();
});
