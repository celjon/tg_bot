<?php

namespace App\Exception;

use Exception;

class InvalidWebhookException extends Exception
{
    /**
     * InvalidWebhookException constructor.
     */
    public function __construct()
    {
        parent::__construct('Invalid webhook data');
    }
}