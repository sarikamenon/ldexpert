<?php

declare(strict_types=1);

use App\Enums\SessionLogStatus;

test('canDelete is true for every status except approved', function () {
    expect(SessionLogStatus::DRAFT->canDelete())->toBeTrue()
        ->and(SessionLogStatus::SUBMITTED->canDelete())->toBeTrue()
        ->and(SessionLogStatus::SENT_BACK->canDelete())->toBeTrue()
        ->and(SessionLogStatus::CANCELLED->canDelete())->toBeTrue()
        ->and(SessionLogStatus::APPROVED->canDelete())->toBeFalse();
});
