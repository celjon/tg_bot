<?php

namespace App\Exception;

use Exception;

class BothubException extends Exception
{
    const DEFAULT_MODEL_NOT_FOUND = 'DEFAULT_MODEL_NOT_FOUND';
    const INVALID_MODEL = 'INVALID_MODEL';
    const CHAT_NOT_FOUND = 'CHAT_NOT_FOUND';
    const USER_NOT_FOUND = 'USER_NOT_FOUND';

    /**
     * BothubException constructor.
     * @param string $message
     * @param int $code
     */
    public function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }
}