<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CannotDeleteBilledScheduleException extends Exception
{
    public function __construct(string $message = 'Cannot delete a schedule that has already been billed.')
    {
        parent::__construct($message);
    }
}
