<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Enums\InvoiceLineType;
use App\Enums\SessionOutcome;

/**
 * Classifies an advance-billing reconciliation adjustment by the session outcome.
 *
 * Shared by both reconcile flows so they emit identical status-based line types:
 *   - AdvanceBillingService (1st-of-month in-run reconcile)
 *   - AdvanceReconciliationService (10th-of-month late catch-up)
 *
 * It only decides the line TYPE and a human label suffix from the outcome. The
 * caller supplies the line AMOUNT, which differs per flow (the in-run flow uses
 * advance-vs-outcome; the catch-up flow uses should_bill − already_billed so it
 * never re-bills what an earlier run already adjusted).
 */
final class AdvanceAdjustmentClassifier
{
    /**
     * The ADJUST_* line type for a given outcome (null session = did-not-occur).
     */
    public function lineTypeFor(?SessionOutcome $outcome): string
    {
        if ($outcome === null) {
            return InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value;
        }

        return match ($outcome) {
            SessionOutcome::SERVICES_ADMINISTERED => InvoiceLineType::ADJUST_RATE_DIFFERENCE->value,
            SessionOutcome::NO_SHOW => InvoiceLineType::ADJUST_NO_SHOW->value,
            SessionOutcome::BILLABLE_CANCELLATION => InvoiceLineType::ADJUST_CANCEL_BILLABLE->value,
            SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT,
            SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER => InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value,
        };
    }

    /**
     * The human-readable label suffix appended to a base description.
     */
    public function descriptionSuffixFor(?SessionOutcome $outcome): string
    {
        if ($outcome === null) {
            return 'session did not occur (full credit)';
        }

        return match ($outcome) {
            SessionOutcome::SERVICES_ADMINISTERED => 'rate adjustment',
            SessionOutcome::NO_SHOW => 'no-show (adjusted to no-show rate)',
            SessionOutcome::BILLABLE_CANCELLATION => 'billable cancellation (adjusted)',
            SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT,
            SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER => "cancelled ({$outcome->label()}, full credit)",
        };
    }
}
