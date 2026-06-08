<?php

declare(strict_types=1);

use App\Domain\Billing\Services\AdvanceAdjustmentClassifier;
use App\Enums\InvoiceLineType;
use App\Enums\SessionOutcome;

beforeEach(function () {
    $this->classifier = new AdvanceAdjustmentClassifier;
});

test('lineTypeFor maps each session outcome to the matching ADJUST_* line type', function (SessionOutcome $outcome, InvoiceLineType $expected) {
    expect($this->classifier->lineTypeFor($outcome))->toBe($expected->value);
})->with([
    'administered → rate difference' => [SessionOutcome::SERVICES_ADMINISTERED, InvoiceLineType::ADJUST_RATE_DIFFERENCE],
    'no-show → no-show' => [SessionOutcome::NO_SHOW, InvoiceLineType::ADJUST_NO_SHOW],
    'billable cancellation → cancel billable' => [SessionOutcome::BILLABLE_CANCELLATION, InvoiceLineType::ADJUST_CANCEL_BILLABLE],
    'non-billable client → cancel non-billable' => [SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT, InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE],
    'non-billable provider → cancel non-billable' => [SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER, InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE],
]);

test('lineTypeFor maps a null outcome (did-not-occur) to a full non-billable credit', function () {
    expect($this->classifier->lineTypeFor(null))
        ->toBe(InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value);
});

test('descriptionSuffixFor returns a human label for each session outcome', function (SessionOutcome $outcome, string $expected) {
    expect($this->classifier->descriptionSuffixFor($outcome))->toBe($expected);
})->with([
    'administered' => [SessionOutcome::SERVICES_ADMINISTERED, 'rate adjustment'],
    'no-show' => [SessionOutcome::NO_SHOW, 'no-show (adjusted to no-show rate)'],
    'billable cancellation' => [SessionOutcome::BILLABLE_CANCELLATION, 'billable cancellation (adjusted)'],
]);

test('descriptionSuffixFor embeds the outcome label for non-billable cancellations', function (SessionOutcome $outcome) {
    expect($this->classifier->descriptionSuffixFor($outcome))
        ->toBe("cancelled ({$outcome->label()}, full credit)")
        ->toContain($outcome->label());
})->with([
    'client' => [SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT],
    'provider' => [SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER],
]);

test('descriptionSuffixFor maps a null outcome to the did-not-occur label', function () {
    expect($this->classifier->descriptionSuffixFor(null))
        ->toBe('session did not occur (full credit)');
});
