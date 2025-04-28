<?php

namespace App\Exception;

use Exception;

class WhisperException extends Exception
{
    /**
     * WhisperException constructor.
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct('Invalid whisper response: ' . $message);
    }
}