<?php

namespace App\Service;

use App\Exception\UrlParserException;
use Exception;

class UrlParser
{
    /**
     * @param string $text
     * @return string
     */
    public static function parseUrls(string $text): string
    {
        $urls = self::parseUrlsFromText($text);
        if (!empty($urls)) {
            foreach ($urls as $url) {
                //Ссылки на ютуб-видео не парсим, так как они отдельно анализируются на бэке
                if (strpos($url, 'https://www.youtube.com/watch') === 0) {
                    continue;
                }
                try {
                    $text .= PHP_EOL . 'I specially copied the data from ' . $url . ' for you: ' . self::parseWebPage($url);
                } catch (Exception $e) {}
            }
        }
        return $text;
    }

    /**
     * @param string $url
     * @return string
     * @throws UrlParserException
     */
    private static function parseWebPage(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://r.jina.ai/' . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        if (curl_errno($ch) || !$data) {
            throw new UrlParserException($url);
        }
        curl_close($ch);
        return $data;
    }

    /**
     * @param string $text
     * @return string[]|null
     */
    private static function parseUrlsFromText(string $text): ?array
    {
        preg_match_all('/(?:(?:(?:ftp|http)[s]*:\/\/|www\.)[^\.]+\.[^ \n]+)/', $text, $matches);
        return !empty($matches[0]) ? $matches[0] : null;
    }
}