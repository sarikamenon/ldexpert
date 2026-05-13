<?php

declare(strict_types=1);

use App\Http\Support\SSAGoalReturnTo;

test('tryFromQuery resolves the student goals token to the enum case', function () {
    expect(SSAGoalReturnTo::tryFromQuery('student_goals'))->toBe(SSAGoalReturnTo::StudentGoalsTab);
});

test('tryFromQuery returns null for unknown or non-string values', function () {
    expect(SSAGoalReturnTo::tryFromQuery('https://evil.example'))->toBeNull()
        ->and(SSAGoalReturnTo::tryFromQuery(null))->toBeNull()
        ->and(SSAGoalReturnTo::tryFromQuery(['student_goals']))->toBeNull();
});
