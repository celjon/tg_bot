<?php

namespace App\Service\EventSource;

use Exception;

class Event
{
    /** @var string[] */
    public $data = [];

    /**
     * Event constructor.
     * @param string $data
     * @throws Exception
     */
    public function __construct(string $data)
    {
        $this->data = explode('data: ', $data);
        if (!$this->data[0]) {
            unset($this->data[0]);
        }
        if (empty($this->data)) {
            throw new Exception('Invalid event');
        }
    }
}