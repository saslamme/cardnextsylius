<?php

declare(strict_types=1);

namespace App\Service\Search;

final class SearchSynonymParser
{
    /**
     * @return list<string>
     */
    public function parse(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $synonyms = [];

        foreach (preg_split('/[\r\n,;]+/u', $value) ?: [] as $synonym) {
            $synonym = trim($synonym);

            if ($synonym === '') {
                continue;
            }

            $synonyms[mb_strtolower($synonym)] ??= $synonym;
        }

        return array_values($synonyms);
    }
}
