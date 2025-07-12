<?php

namespace ElliottLawson\Daytona\Exceptions;

use Exception;

class DaytonaException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}