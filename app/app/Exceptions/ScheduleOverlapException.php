<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ScheduleOverlapException extends Exception
{
    public function __construct(string $message = 'Schedule overlap detected.')
    {
        parent::__construct($message);
    }
}
