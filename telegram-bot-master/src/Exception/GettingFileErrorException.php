<?php

namespace App\Exception;

use Exception;

class GettingFileErrorException extends Exception
{
    /**
     * FileIsTooBigException constructor.
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct('Invalid Telegram API response: ' . $message);
    }
}