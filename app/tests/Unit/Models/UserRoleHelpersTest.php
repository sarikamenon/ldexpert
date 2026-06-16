<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

test('isStudent returns true only for the student role', function () {
    expect((new User(['role' => Role::STUDENT]))->isStudent())->toBeTrue();
    expect((new User(['role' => Role::THERAPIST]))->isStudent())->toBeFalse();
    expect((new User(['role' => Role::ADMIN]))->isStudent())->toBeFalse();
    expect((new User(['role' => Role::PARENT]))->isStudent())->toBeFalse();
});
