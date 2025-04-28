<?php

namespace App\Exception;

use Exception;

class TelegramUpdateException extends Exception
{
    /**
     * TelegramUpdateException constructor.
     * @param string $json
     */
    public function __construct(string $json)
    {
        parent::__construct('Wrong Telegram update: ' . $json);
    }
}