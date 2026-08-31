<?php

declare(strict_types=1);

namespace App\Tests\International;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ForeignTranslationLanguageQualityTest extends TestCase
{
    private const LOCALES = ['da_DK', 'es_ES', 'it_IT', 'nl_NL', 'sv_SE'];

    /** @var list<string> */
    private const ALLOWED_IDENTICAL_VALUES = [
        'Auto-ID',
        'Barcode',
        'Cardnext',
        'Konfiguration',
        'Downloads',
        'RFID',
        'Support',
        'Alle %category%',
    ];

    #[DataProvider('localeProvider')]
    public function testStorefrontDoesNotContainGermanFragments(string $locale): void
    {
        $translations = $this->storefrontTranslations($locale);
        $germanFragments = [
            '/\b(?:für|und|oder|mit|ohne)\b/u',
            '/\b(?:Beratung|Warenkorb|Hersteller|Produkte?|Verlässlichkeit|verfügbar|auswählen|anzeigen)\b/u',
            '/\b(?:Impressum|Datenschutz|Rechtliches)\b/u',
            '/\b(?:Nach oben|Über uns)\b/u',
        ];

        $violations = [];
        foreach ($translations as $key => $value) {
            foreach ($germanFragments as $pattern) {
                if (1 === preg_match($pattern, $value)) {
                    $violations[] = sprintf('%s: "%s"', $key, $value);

                    break;
                }
            }
        }

        self::assertSame([], $violations, sprintf("German fragments found in %s:\n%s", $locale, implode("\n", $violations)));
    }

    #[DataProvider('localeProvider')]
    public function testLongStorefrontValuesAreNotIdenticalToGerman(string $locale): void
    {
        $german = $this->storefrontTranslations('de');
        $translations = $this->storefrontTranslations($locale);
        $violations = [];

        foreach ($translations as $key => $value) {
            if (!isset($german[$key]) || $value !== $german[$key] || mb_strlen($value) < 12) {
                continue;
            }

            if (in_array($value, self::ALLOWED_IDENTICAL_VALUES, true)) {
                continue;
            }

            $violations[] = sprintf('%s: "%s"', $key, $value);
        }

        self::assertSame([], $violations, sprintf("German values copied to %s:\n%s", $locale, implode("\n", $violations)));
    }

    /** @return iterable<string, array{string}> */
    public static function localeProvider(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    /** @return array<string, string> */
    private function storefrontTranslations(string $locale): array
    {
        $file = dirname(__DIR__, 2) . sprintf('/translations/messages.%s.yaml', $locale);
        $catalogue = Yaml::parseFile($file);
        self::assertIsArray($catalogue);
        self::assertIsArray($catalogue['cardnext']['storefront'] ?? null);

        return $this->flatten($catalogue['cardnext']['storefront']);
    }

    /**
     * @param array<array-key, mixed> $translations
     *
     * @return array<string, string>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $flattened = [];
        foreach ($translations as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flattened += $this->flatten($value, $path);
            } elseif (is_string($value)) {
                $flattened[$path] = $value;
            }
        }

        return $flattened;
    }
}
