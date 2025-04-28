<?php

namespace App\Exception;

use Exception;

class OpenAIException extends Exception
{
    /**
     * OpenAIException constructor.
     * @param string|null $message
     */
    public function __construct(?string $message = null)
    {
        parent::__construct('OpenAI request error' . ($message ? ': ' . $message : ''));
    }
}