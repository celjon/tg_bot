<?php

namespace App\Service;

class FormatValidator
{
    /**
     * @param string $string
     * @return bool
     */
    public static function isUsername(string $string): bool
    {
        return !!preg_match('/^@?[A-Za-z0-9_]+$/', $string);
    }

    /**
     * @param string $string
     * @return bool
     */
    public static function isEmail(string $string): bool
    {
        return !!filter_var($string, FILTER_VALIDATE_EMAIL);
    }
}