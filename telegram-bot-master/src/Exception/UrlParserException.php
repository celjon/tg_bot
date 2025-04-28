<?php

namespace App\Exception;

use Exception;

class UrlParserException extends Exception
{
    /** @var string */
    private $url;

    /**
     * UrlParserException constructor.
     * @param string $url
     */
    public function __construct(string $url)
    {
        $this->url = $url;
        parent::__construct('Impossible to parse the URL ' . $url);
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}