<?php

namespace App\Exception;

use Exception;

class WrongFormatException extends Exception
{
    /**
     * WrongFormatException constructor.
     * @param string $data
     */
    public function __construct(string $data)
    {
        parent::__construct('Wrong format: ' . $data);
    }
}