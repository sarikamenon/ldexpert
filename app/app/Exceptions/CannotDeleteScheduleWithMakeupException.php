<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CannotDeleteScheduleWithMakeupException extends Exception
{
    public function __construct(string $message = 'Cannot delete a session that has an active make-up request. Resolve or decline the make-up first.')
    {
        parent::__construct($message);
    }
}
