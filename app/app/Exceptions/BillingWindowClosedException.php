<?php

declare(strict_types=1);

namespace App\Exceptions;

use Carbon\Carbon;
use RuntimeException;

final class BillingWindowClosedException extends RuntimeException
{
    public readonly Carbon $cutoff;

    public function __construct(Carbon $cutoff)
    {
        $this->cutoff = $cutoff;

        parent::__construct(
            "The billing window for this session's week closed on "
            .$cutoff->format('l, M j, Y')
            .'. Session logs can no longer be created or edited for this date.'
        );
    }
}
