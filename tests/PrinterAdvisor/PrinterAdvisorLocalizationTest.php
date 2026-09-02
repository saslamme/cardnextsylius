<?php

declare(strict_types=1);

namespace Tests\PrinterAdvisor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class PrinterAdvisorLocalizationTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string, string}> */
    public static function localizedCopyProvider(): iterable
    {
        yield 'German' => ['de', 'Welcher Kartendrucker passt zu Ihnen?', 'Wie viele Karten möchten Sie ungefähr drucken?', 'Weiter', 'Kartendrucker-Guide: Worauf Sie beim Kauf achten sollten'];
        yield 'Dutch' => ['nl_NL', 'Welke kaartprinter past bij uw organisatie?', 'Hoeveel kaarten wilt u ongeveer printen?', 'Volgende', 'Koopgids voor kaartprinters: waar moet u op letten?'];
        yield 'Danish' => ['da_DK', 'Hvilken kortprinter passer til jer?', 'Hvor mange kort forventer I cirka at printe?', 'Næste', 'Guide til kortprintere: det skal I overveje før køb'];
        yield 'Swedish' => ['sv_SE', 'Vilken kortskrivare passar er?', 'Ungefär hur många kort vill ni skriva ut?', 'Nästa', 'Guide till kortskrivare: detta bör ni tänka på'];
        yield 'Italian' => ['it_IT', 'Quale stampante di carte fa al caso vostro?', 'Quante carte prevedete di stampare?', 'Avanti', 'Guida alle stampanti di carte: cosa valutare prima dell’acquisto'];
        yield 'Spanish' => ['es_ES', '¿Qué impresora de tarjetas se adapta a sus necesidades?', '¿Cuántas tarjetas desea imprimir aproximadamente?', 'Siguiente', 'Guía de impresoras de tarjetas: aspectos que debe tener en cuenta'];
    }

    #[DataProvider('localizedCopyProvider')]
    public function testAdvisorHasLocalizedUiQuestionNavigationAndGuide(string $locale, string $title, string $question, string $next, string $guide): void
    {
        $messages = Yaml::parseFile(dirname(__DIR__, 2).'/translations/messages.'.$locale.'.yaml');

        self::assertSame($title, $messages['cardnext.storefront.printer_advisor.hero.title']);
        self::assertSame($question, $messages['cardnext.storefront.printer_advisor.questions.volume.title']);
        self::assertSame($next, $messages['cardnext.storefront.printer_advisor.navigation.next']);
        self::assertSame($guide, $messages['cardnext.storefront.printer_advisor.guide.title']);

        if ($locale !== 'de') {
            $advisorCopy = implode("\n", array_filter($messages, static fn (string $key): bool => str_starts_with($key, 'cardnext.storefront.printer_advisor.'), ARRAY_FILTER_USE_KEY));
            self::assertStringNotContainsString('Welcher Kartendrucker passt zu Ihnen?', $advisorCopy);
            self::assertStringNotContainsString('Noch keine Auswahl', $advisorCopy);
            self::assertStringNotContainsString('Beste Empfehlung', $advisorCopy);
        }
    }

    public function testEveryLocaleHasTheSameCompleteAdvisorCatalogue(): void
    {
        $root = dirname(__DIR__, 2);
        $locales = ['de', 'de_AT', 'en', 'nl_NL', 'da_DK', 'sv_SE', 'it_IT', 'es_ES'];
        $expected = null;

        foreach ($locales as $locale) {
            $messages = Yaml::parseFile($root.'/translations/messages.'.$locale.'.yaml');
            $keys = array_keys(array_filter($messages, static fn (string $key): bool => str_starts_with($key, 'cardnext.storefront.printer_advisor.'), ARRAY_FILTER_USE_KEY));
            sort($keys);
            $expected ??= $keys;
            self::assertSame($expected, $keys, sprintf('The %s advisor catalogue is incomplete.', $locale));
        }
    }

    public function testTemplatesAndJavascriptContainNoGermanPresentationCopy(): void
    {
        $root = dirname(__DIR__, 2);
        $presentation = file_get_contents($root.'/templates/shop/printer_advisor/index.html.twig')
            .file_get_contents($root.'/templates/shop/printer_advisor/_guide.html.twig')
            .file_get_contents($root.'/assets/shop/printer-advisor.js');

        self::assertStringNotContainsString('Schritt ', $presentation);
        self::assertStringNotContainsString('Noch keine Auswahl', $presentation);
        self::assertStringNotContainsString('Empfehlung wird berechnet', $presentation);
        self::assertStringNotContainsString('Persönliche Beratung', $presentation);
    }
}
