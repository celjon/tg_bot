<?php

namespace App\Service;

class CallbackQueryService
{
    public function encode(string $type, ?array $data = null): string
    {
        if (is_null($data)) {
            return $type;
        }
        $queryString = http_build_query($data);
        return $type . '?' . $queryString;
    }

    public function decode(string $data): array
    {
        $parsedData = parse_url($data);
        parse_str($parsedData['query'], $queryParams);

        return [
            'type' => $parsedData['path'],
            'data' => $queryParams,
        ];
    }
}