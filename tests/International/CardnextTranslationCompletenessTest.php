<?php

declare(strict_types=1);

namespace App\Tests\International;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class CardnextTranslationCompletenessTest extends TestCase
{
    #[DataProvider('localeProvider')]
    public function testLocaleContainsEveryGermanLeafTranslationKey(string $locale): void
    {
        $directory = dirname(__DIR__, 2) . '/translations';
        $referenceCatalogue = Yaml::parseFile($directory . '/messages.de.yaml');
        $translatedCatalogue = Yaml::parseFile(sprintf('%s/messages.%s.yaml', $directory, $locale));
        self::assertIsArray($referenceCatalogue);
        self::assertIsArray($translatedCatalogue);
        $reference = $this->leafKeys($referenceCatalogue);
        $translated = $this->leafKeys($translatedCatalogue);

        self::assertSame([], array_values(array_diff($reference, $translated)), sprintf('Missing Cardnext translation keys for %s.', $locale));
    }

    /** @return iterable<string, array{string}> */
    public static function localeProvider(): iterable
    {
        foreach (['de_AT', 'da_DK', 'es_ES', 'it_IT', 'nl_NL', 'sv_SE'] as $locale) {
            yield $locale => [$locale];
        }
    }

    /**
     * @param array<array-key, mixed> $translations
     *
     * @return list<string>
     */
    private function leafKeys(array $translations, string $prefix = ''): array
    {
        $keys = [];
        foreach ($translations as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                array_push($keys, ...$this->leafKeys($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }
}
