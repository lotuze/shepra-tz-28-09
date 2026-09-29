<?php

declare(strict_types=1);

namespace App\Import;

final class ImageUrlExtractor
{
    /** @return list<string> */
    public function extract(mixed $packaging, mixed $photos): array
    {
        $values = [];
        foreach ([$packaging, $photos] as $source) {
            if (!is_scalar($source)) { continue; }
            foreach (explode(',', (string) $source) as $url) {
                $url = trim($url);
                if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) { continue; }
                if (!in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) { continue; }
                $values[$url] = true;
            }
        }
        return array_keys($values);
    }
}
