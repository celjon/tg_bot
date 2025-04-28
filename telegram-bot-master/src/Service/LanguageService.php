<?php

namespace App\Service;

class LanguageService
{
    private const LANGUAGES = ['en', 'ru'];
    private const DEFAULT_LANG = 'en';
    private const LANG_PATH = __DIR__ . '/../../lang/';

    /** @var string[] */
    private $languageConstants;

    /**
     * LanguageService constructor.
     * @param string|null $languageCode
     */
    public function __construct(?string $languageCode)
    {
        $languageCode = strtolower($languageCode);
        if (!$languageCode || !in_array($languageCode, self::LANGUAGES)) {
            $langFile = self::LANG_PATH . self::DEFAULT_LANG . '.json';
        } else {
            $langFile = self::LANG_PATH . $languageCode . '.json';
        }
        $this->languageConstants = json_decode(file_get_contents($langFile), true);
    }

    /**
     * @param string $key
     * @param array $params
     * @return string
     */
    public function getLocalizedString(string $key, array $params = []): string
    {
        if (isset($this->languageConstants[$key])) {
            return sprintf($this->languageConstants[$key],...$params);
        } else {
            return str_replace('L_', '', $key);
        }
    }

    /**
     * @param string $key
     * @return string
     */
    public function getLocalizedErrorString(string $key): string
    {
        if (isset($this->languageConstants['L_ERROR_' . $key])) {
            return $this->languageConstants['L_ERROR_' . $key];
        } else {
            $key = str_replace('_', '\\_', $key);
            $key = str_replace('*', '\\*', $key);
            return sprintf($this->languageConstants['L_ERROR_UNKNOWN_ERROR'], $key);
        }
    }
}