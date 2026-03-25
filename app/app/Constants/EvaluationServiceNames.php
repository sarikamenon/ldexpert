<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Catalog service names that denote evaluation access for QGlob student eligibility.
 */
final class EvaluationServiceNames
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'Evaluations (Speech, Occupational)',
            'Evaluations - Academic/Cognitive',
        ];
    }
}
