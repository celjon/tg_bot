<?php

namespace App\Exception;

use Exception;

class TelegramApiException extends Exception
{
    /**
     * TelegramApiException constructor.
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}