<?php

namespace App\Exception;

use Exception;

class ProhibitedContentException extends Exception
{
    /**
     * ProhibitedContentException constructor
     * @param string $message
     * @param int $code
     */
    public function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }
}