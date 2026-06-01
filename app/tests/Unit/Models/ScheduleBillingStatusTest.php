<?php

declare(strict_types=1);

use App\Enums\BillingStatus;
use App\Models\Schedule;

test('isBilled is true only when billing status is billed', function () {
    expect((new Schedule(['billing_status' => BillingStatus::BILLED]))->isBilled())->toBeTrue()
        ->and((new Schedule(['billing_status' => BillingStatus::PENDING]))->isBilled())->toBeFalse()
        ->and((new Schedule(['billing_status' => BillingStatus::NOT_BILLABLE]))->isBilled())->toBeFalse();
});
