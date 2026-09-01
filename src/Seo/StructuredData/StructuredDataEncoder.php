<?php

declare(strict_types=1);

namespace App\Seo\StructuredData;

final class StructuredDataEncoder
{
    /** @param array<string, mixed>|null $data */
    public function encode(?array $data): string
    {
        if ($data === null) {
            return '';
        }

        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
