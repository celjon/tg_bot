<?php

namespace App\Exception;

use Exception;

class MidjourneyException extends Exception
{
    /**
     * MidjourneyError constructor.
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}