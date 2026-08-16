<?php

declare(strict_types=1);

namespace App\LegacyImport;

/**
 * Adds conservative, product-family-aware attribute extraction on top of the
 * structured legacy-description parser. No external services are used.
 */
final readonly class LegacyRecordAttributeExtractor
{
    public function __construct(private LegacyDescriptionAttributeExtractor $descriptionExtractor)
    {
    }

    /** @return array<string, mixed> */
    public function extract(LegacyProductRecord $record): array
    {
        $attributes = $this->descriptionExtractor->extract($record->description);
        $plain = trim($record->name."\n".$this->plainText($record->description));

        $this->addWarranty($attributes, $plain);

        if ($this->hasTaxon($record, 'card_printers')) {
            $this->extractCardPrinter($attributes, $record->name, $plain);
        }
        if ($this->hasTaxon($record, 'ribbons')) {
            $this->extractRibbon($attributes, $record->name, $plain);
        }
        if ($this->hasTaxonPrefix($record, 'id_accessories')) {
            $this->extractIdAccessory($attributes, $record, $plain);
        }
        if ($this->hasTaxonPrefix($record, 'rfid_readers')) {
            $this->extractRfidReader($attributes, $record, $plain);
        }
        if ($this->hasTaxonPrefix($record, 'rfid_transponder')) {
            $this->extractRfidTransponder($attributes, $record, $plain);
        }
        if ($this->hasTaxonPrefix($record, 'plastic_cards')) {
            $this->extractPlasticCard($attributes, $record, $plain);
        }
        if ($this->hasTaxon($record, 'barcode_scanners')) {
            $this->extractBarcodeScanner($attributes, $record->name, $plain);
        }
        if ($this->hasTaxon($record, 'card_software')) {
            $this->extractCardSoftware($attributes, $record->name, $plain);
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function extractCardPrinter(array &$attributes, string $name, string $plain): void
    {
        $fold = self::fold($name.' '.$plain);

        if (str_contains($fold, 'retransfer') || str_contains($fold, 'reversetransfer')) {
            $this->add($attributes, 'CN_PRINTER_TECHNOLOGY', ['retransfer']);
        } elseif (
            str_contains($fold, 'direktkartendruck') ||
            str_contains($fold, 'directtocard') ||
            str_contains($fold, 'dyesublimation') ||
            str_contains($fold, 'thermosublimation')
        ) {
            $this->add($attributes, 'CN_PRINTER_TECHNOLOGY', ['direct_to_card']);
        }

        if (str_contains($fold, 'duplex') || str_contains($fold, 'beidseit')) {
            $this->add($attributes, 'CN_PRINT_SIDES', ['duplex']);
        } elseif (str_contains($fold, 'simplex') || str_contains($fold, 'einseit')) {
            $this->add($attributes, 'CN_PRINT_SIDES', ['single']);
        }

        $this->add($attributes, 'CN_PRINT_RESOLUTION', $this->resolution($plain));

        $modes = [];
        if (preg_match('/\b(?:Farb(?:druck)?|Vollfarb|Color|Colour)\b/iu', $plain)) {
            $modes[] = 'color';
        }
        if (preg_match('/\b(?:Monochrom|Schwarz[- ]?Weiß)\b/iu', $plain)) {
            $modes[] = 'monochrome';
        }
        $this->add($attributes, 'CN_PRINT_MODE', $modes);

        if (preg_match('/\bCR\s*-?\s*80\b/iu', $plain) || preg_match('/85[,.]6\s*(?:x|×)\s*5[34][,.]?[0-9]*\s*mm/iu', $plain)) {
            $this->add($attributes, 'CN_CARD_FORMAT', ['cr80']);
        }
        if (preg_match('/\bCR\s*-?\s*79\b/iu', $plain)) {
            $this->add($attributes, 'CN_CARD_FORMAT', ['cr79']);
        }

        $this->add($attributes, 'CN_CONNECTIVITY', $this->connectivity($plain, ['usb', 'ethernet', 'wifi', 'bluetooth', 'serial']));
        $this->add($attributes, 'CN_ENCODING_OPTIONS', $this->encodingOptions($plain));
    }

    /** @param array<string, mixed> $attributes */
    private function extractRibbon(array &$attributes, string $name, string $plain): void
    {
        $foldName = self::fold($name);
        if (str_contains($foldName, 'reinig')) {
            return;
        }

        $type = null;
        if (str_contains($foldName, 'ymckok')) {
            $type = ['ymckok'];
        } elseif (str_contains($foldName, 'ymcko')) {
            $type = ['ymcko'];
        } elseif (preg_match('/\bYMCK\b/iu', $name)) {
            $type = ['ymck'];
        } elseif (str_contains($foldName, 'overlay') || str_contains($foldName, 'schutzfilm')) {
            $type = ['overlay'];
        } elseif (str_contains($foldName, 'retransfer') || str_contains($foldName, 'transferfilm')) {
            $type = ['retransfer'];
        } elseif (
            preg_match('/\bFarbband\s+(?:K\b|Schwarz\b|Black\b|Gold\b|Silber\b|Silver\b|Blau\b|Blue\b|Rot\b|Red\b|Weiß\b|White\b|Grün\b|Green\b)/iu', $name) ||
            str_contains($foldName, 'monochrom')
        ) {
            $type = ['monochrome'];
        } elseif (str_contains($foldName, 'farbband')) {
            $type = ['other'];
        }
        $this->add($attributes, 'CN_RIBBON_TYPE', $type);

        if (preg_match('/\bFarbband\s+(.+?)(?:\s*\(|\s+-\s+|$)/iu', $name, $match)) {
            $color = trim($match[1]);
            $color = preg_replace('/\b(?:Premium|Original)\b/iu', '', $color) ?? $color;
            $color = trim(preg_replace('/\s+/', ' ', $color) ?? $color);
            if ($color !== '' && mb_strlen($color) <= 80) {
                $this->add($attributes, 'CN_RIBBON_COLOR', $color);
            }
        }

        $yield = $this->quantityNearUnit($name, ['prints?', 'images?', 'drucke?', 'kartenseiten', 'karten']);
        if ($yield === null) {
            $yield = $this->quantityNearUnit($plain, ['prints?', 'images?', 'drucke?', 'kartenseiten']);
        }
        $this->add($attributes, 'CN_RIBBON_YIELD', $yield);
    }

    /** @param array<string, mixed> $attributes */
    private function extractIdAccessory(array &$attributes, LegacyProductRecord $record, string $plain): void
    {
        $type = null;
        foreach ($record->taxonCodes as $taxon) {
            $type = match (true) {
                str_contains($taxon, 'holders') => 'holder',
                str_contains($taxon, 'lanyards') => 'lanyard',
                str_contains($taxon, 'clips') => 'clip',
                str_contains($taxon, 'reels') => 'reel',
                default => $type,
            };
        }
        $this->add($attributes, 'CN_ACCESSORY_TYPE', $type !== null ? [$type] : null);
        $this->add($attributes, 'CN_PRODUCT_COLOR', $this->colors($record->name));

        $fold = self::fold($record->name.' '.$plain);
        if (str_contains($fold, 'horizontal') || str_contains($fold, 'querformat')) {
            $this->add($attributes, 'CN_CARD_ORIENTATION', ['landscape']);
        } elseif (str_contains($fold, 'vertikal') || str_contains($fold, 'hochformat')) {
            $this->add($attributes, 'CN_CARD_ORIENTATION', ['portrait']);
        }

        if (preg_match('/(?:für|bis\s+zu)\s+(\d{1,2})\s+Karten\b/iu', $plain, $m)) {
            $this->add($attributes, 'CN_CARD_CAPACITY', (int) $m[1]);
        }

        $materials = [];
        foreach ([
            'polycarbonat' => 'Polycarbonat',
            'hartplastik' => 'Hartplastik',
            'weichplastik' => 'Weichplastik',
            'kunststoff' => 'Kunststoff',
            'metall' => 'Metall',
            'silikon' => 'Silikon',
            'vinyl' => 'Vinyl',
            'pvc' => 'PVC',
        ] as $needle => $label) {
            if (str_contains($fold, self::fold($needle))) {
                $materials[] = $label;
            }
        }
        if ($materials !== []) {
            $this->add($attributes, 'CN_PRODUCT_MATERIAL', implode(' / ', array_values(array_unique($materials))));
        }

        $attachments = [];
        foreach ([
            'krokodil' => 'Krokodilklemme',
            'karabiner' => 'Karabiner',
            'schlusselring' => 'Schlüsselring',
            'sicherheitsverschluss' => 'Sicherheitsverschluss',
            'clip' => 'Clip',
            'jojo' => 'Jojo',
        ] as $needle => $label) {
            if (str_contains($fold, $needle)) {
                $attachments[] = $label;
            }
        }
        if ($attachments !== []) {
            $this->add($attributes, 'CN_ATTACHMENT_TYPE', implode(' / ', array_values(array_unique($attachments))));
        }
    }

    /** @param array<string, mixed> $attributes */
    private function extractRfidReader(array &$attributes, LegacyProductRecord $record, string $plain): void
    {
        $this->add($attributes, 'CN_RFID_FREQUENCY', $this->rfidFrequencies($plain));
        $this->add($attributes, 'CN_RFID_TECHNOLOGIES', $this->rfidTechnologies($plain));
        $this->add($attributes, 'CN_RFID_INTERFACE', $this->connectivity($plain, ['usb', 'rs232', 'wiegand', 'osdp', 'ethernet', 'bluetooth']));

        $fold = self::fold($record->name.' '.$plain);
        if ($this->hasTaxon($record, 'rfid_readers_oem') || str_contains($fold, 'oem') || str_contains($fold, 'embedded')) {
            $this->add($attributes, 'CN_RFID_FORM_FACTOR', ['oem']);
        } elseif (str_contains($fold, 'panelmount') || str_contains($fold, 'einbaumontage') || str_contains($fold, 'einbau')) {
            $this->add($attributes, 'CN_RFID_FORM_FACTOR', ['panel_mount']);
        } elseif (str_contains($fold, 'surfacemount') || str_contains($fold, 'aufbaumontage') || str_contains($fold, 'wandmontage')) {
            $this->add($attributes, 'CN_RFID_FORM_FACTOR', ['surface_mount']);
        } elseif (str_contains($fold, 'desktop') || str_contains($fold, 'tischgerat') || str_contains($fold, 'usbreader')) {
            $this->add($attributes, 'CN_RFID_FORM_FACTOR', ['desktop']);
        }

        $modes = [];
        if (str_contains($fold, 'keyboard') || str_contains($fold, 'keystroke') || str_contains($fold, 'tastaturemulation')) {
            $modes[] = 'keyboard';
        }
        if (str_contains($fold, 'virtualcom') || str_contains($fold, 'vcom')) {
            $modes[] = 'virtual_com';
        }
        if (str_contains($fold, 'sdk') || preg_match('/\bAPI\b/u', $plain)) {
            $modes[] = 'sdk';
        }
        if (str_contains($fold, 'wiegand') || str_contains($fold, 'osdp')) {
            $modes[] = 'wiegand_osdp';
        }
        $this->add($attributes, 'CN_RFID_OUTPUT_MODE', $modes);
        $this->add($attributes, 'CN_IP_RATING', $this->ipRating($plain));
    }

    /** @param array<string, mixed> $attributes */
    private function extractRfidTransponder(array &$attributes, LegacyProductRecord $record, string $plain): void
    {
        $this->add($attributes, 'CN_RFID_FREQUENCY', $this->rfidFrequencies($plain));
        $this->add($attributes, 'CN_RFID_TECHNOLOGIES', $this->rfidTechnologies($record->name.' '.$plain));
        $this->add($attributes, 'CN_PRODUCT_COLOR', $this->colors($record->name.' '.$plain));
        $this->add($attributes, 'CN_IP_RATING', $this->ipRating($plain));

        $fold = self::fold($record->name.' '.$plain);
        $materials = [];
        foreach (['pvc' => 'PVC', 'abs' => 'ABS', 'kunststoff' => 'Kunststoff', 'silikon' => 'Silikon', 'polycarbonat' => 'Polycarbonat'] as $needle => $label) {
            if (str_contains($fold, $needle)) {
                $materials[] = $label;
            }
        }
        if ($materials !== []) {
            $this->add($attributes, 'CN_PRODUCT_MATERIAL', implode(' / ', array_values(array_unique($materials))));
        }

        $this->add($attributes, 'CN_CARD_CHIP', $this->chipName($record->name.' '.$plain));
    }

    /** @param array<string, mixed> $attributes */
    private function extractPlasticCard(array &$attributes, LegacyProductRecord $record, string $plain): void
    {
        $fold = self::fold($record->name.' '.$plain);

        $material = null;
        if (str_contains($fold, 'polycarbonat')) {
            $material = ['polycarbonate'];
        } elseif (str_contains($fold, 'petg')) {
            $material = ['petg'];
        } elseif (str_contains($fold, 'pvc')) {
            $material = ['pvc'];
        } elseif (str_contains($fold, 'abs')) {
            $material = ['abs'];
        }
        $this->add($attributes, 'CN_CARD_MATERIAL', $material);

        if (preg_match('/\bCR\s*-?\s*80\b/iu', $plain) || preg_match('/85[,.]6\s*(?:x|×)\s*5[34][,.]?[0-9]*\s*mm/iu', $plain)) {
            $this->add($attributes, 'CN_CARD_FORMAT', ['cr80']);
        }
        if (preg_match('/\bCR\s*-?\s*79\b/iu', $plain)) {
            $this->add($attributes, 'CN_CARD_FORMAT', ['cr79']);
        }

        if (preg_match('/\b(0[,.](?:25|30|50|76|80|84)|30\s*mil)\s*(mm)?\b/iu', $record->name.' '.$plain, $m)) {
            $value = trim($m[1].($m[2] ?? ''));
            $this->add($attributes, 'CN_CARD_THICKNESS', $value);
        }

        $this->add($attributes, 'CN_PRODUCT_COLOR', $this->colors($record->name));

        $surface = [];
        if (str_contains($fold, 'matt')) {
            $surface[] = 'matte';
        }
        if (str_contains($fold, 'glanz') || str_contains($fold, 'gloss')) {
            $surface[] = 'glossy';
        }
        if (str_contains($fold, 'unterschriftenfeld') || str_contains($fold, 'signaturfeld')) {
            $surface[] = 'signature';
        }
        $this->add($attributes, 'CN_CARD_SURFACE', $surface);

        if (str_contains($fold, 'hico')) {
            $this->add($attributes, 'CN_MAGNETIC_STRIPE', ['hico']);
        } elseif (str_contains($fold, 'loco')) {
            $this->add($attributes, 'CN_MAGNETIC_STRIPE', ['loco']);
        }

        $this->add($attributes, 'CN_RFID_FREQUENCY', $this->rfidFrequencies($record->name.' '.$plain));
        $this->add($attributes, 'CN_CARD_CHIP', $this->chipName($record->name.' '.$plain));
    }

    /** @param array<string, mixed> $attributes */
    private function extractBarcodeScanner(array &$attributes, string $name, string $plain): void
    {
        $fold = self::fold($name.' '.$plain);
        $dimensions = [];
        if (preg_match('/\b1D\b/iu', $name.' '.$plain)) {
            $dimensions[] = '1d';
        }
        if (preg_match('/\b2D\b/iu', $name.' '.$plain) || str_contains($fold, 'qrcode')) {
            $dimensions[] = '2d';
        }
        if (preg_match('/\bDPM\b/iu', $name.' '.$plain)) {
            $dimensions[] = 'dpm';
        }
        $this->add($attributes, 'CN_BARCODE_DIMENSION', $dimensions);

        if (str_contains($fold, 'areaimager')) {
            $this->add($attributes, 'CN_SCAN_ENGINE', ['area_imager']);
        } elseif (str_contains($fold, 'linearimager')) {
            $this->add($attributes, 'CN_SCAN_ENGINE', ['linear_imager']);
        } elseif (preg_match('/\bLaser\b/iu', $plain)) {
            $this->add($attributes, 'CN_SCAN_ENGINE', ['laser']);
        }

        $interfaces = $this->connectivity($name.' '.$plain, ['usb', 'rs232', 'bluetooth', 'wifi']);
        $this->add($attributes, 'CN_SCANNER_INTERFACES', $interfaces);
        if (array_intersect($interfaces, ['bluetooth', 'wifi']) !== []) {
            $this->add($attributes, 'CN_WIRELESS', true);
        }
        $this->add($attributes, 'CN_IP_RATING', $this->ipRating($plain));
    }

    /** @param array<string, mixed> $attributes */
    private function extractCardSoftware(array &$attributes, string $name, string $plain): void
    {
        if (preg_match('/\bcardpresso\s+(XXS|XS|XM|XL|XXL)\b/iu', $name, $m)) {
            $this->add($attributes, 'CN_SOFTWARE_LICENSE', 'cardPresso '.strtoupper($m[1]));
        }

        $systems = [];
        if (preg_match('/\bWindows\b/iu', $plain)) {
            $systems[] = 'Windows';
        }
        if (preg_match('/\bmacOS\b|\bMac\s*OS\b/iu', $plain)) {
            $systems[] = 'macOS';
        }
        if ($systems !== []) {
            $this->add($attributes, 'CN_SUPPORTED_OS', implode(', ', array_values(array_unique($systems))));
        }
    }

    /** @param array<string, mixed> $attributes */
    private function addWarranty(array &$attributes, string $plain): void
    {
        if (preg_match('/(?:Garantie|Warranty)[^\d]{0,25}(\d{1,3})\s*(Monate?|Months?)\b/iu', $plain, $m)) {
            $this->add($attributes, 'CN_WARRANTY_MONTHS', (int) $m[1]);
            return;
        }
        if (preg_match('/(?:Garantie|Warranty)[^\d]{0,25}(\d{1,2})\s*(Jahre?|Years?)\b/iu', $plain, $m)) {
            $this->add($attributes, 'CN_WARRANTY_MONTHS', (int) $m[1] * 12);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function add(array &$attributes, string $code, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        if (!array_key_exists($code, $attributes)) {
            $attributes[$code] = $value;
            return;
        }

        if (is_array($attributes[$code]) && is_array($value)) {
            $attributes[$code] = array_values(array_unique([...$attributes[$code], ...$value]));
        }
    }

    /** @return list<string> */
    private function colors(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        foreach ([
            'schwarz' => 'black', 'black' => 'black',
            'weiss' => 'white', 'white' => 'white',
            'grau' => 'grey', 'grey' => 'grey',
            'silber' => 'silver', 'silver' => 'silver',
            'blau' => 'blue', 'blue' => 'blue',
            'rot' => 'red', 'red' => 'red',
            'transparent' => 'transparent',
        ] as $needle => $choice) {
            if (str_contains($fold, $needle)) {
                $result[] = $choice;
            }
        }
        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function rfidFrequencies(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, '125khz') || str_contains($fold, '1342khz') || preg_match('/\bLF\b/u', $value)) {
            $result[] = 'lf_125';
        }
        if (str_contains($fold, '1356mhz') || preg_match('/\bHF\b/u', $value) || preg_match('/\bNFC\b/iu', $value)) {
            $result[] = 'hf_1356';
        }
        if (preg_match('/\bUHF\b/iu', $value) || str_contains($fold, '860960mhz') || str_contains($fold, '868mhz') || str_contains($fold, '915mhz')) {
            $result[] = 'uhf';
        }
        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function rfidTechnologies(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'mifareclassic') || preg_match('/\bMIFARE\s+(?:1K|4K)\b/iu', $value)) {
            $result[] = 'mifare_classic';
        }
        if (str_contains($fold, 'desfire')) {
            $result[] = 'mifare_desfire';
        }
        if (str_contains($fold, 'ntag')) {
            $result[] = 'ntag';
        }
        if (str_contains($fold, 'legic')) {
            $result[] = 'legic';
        }
        if (str_contains($fold, 'hidprox')) {
            $result[] = 'hid_prox';
        }
        if (str_contains($fold, 'iclass') || str_contains($fold, 'seos')) {
            $result[] = 'hid_iclass';
        }
        if (str_contains($fold, 'em4102') || str_contains($fold, 'em4200')) {
            $result[] = 'em4102';
        }
        if (str_contains($fold, 'felica')) {
            $result[] = 'felica';
        }
        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function connectivity(string $value, array $allowed): array
    {
        $value = preg_replace('/\([^)]*(?:optional|modellabh[aä]ngig)[^)]*\)/iu', '', $value) ?? $value;
        $fold = self::fold($value);
        $map = [
            'usb' => ['usb'],
            'ethernet' => ['ethernet'],
            'wifi' => ['wifi', 'wlan'],
            'bluetooth' => ['bluetooth', 'bt'],
            'serial' => ['rs232', 'seriell'],
            'rs232' => ['rs232', 'seriell'],
            'wiegand' => ['wiegand'],
            'osdp' => ['osdp'],
        ];
        $result = [];
        foreach ($allowed as $choice) {
            foreach ($map[$choice] ?? [$choice] as $needle) {
                if (str_contains($fold, self::fold($needle))) {
                    $result[] = $choice;
                    break;
                }
            }
        }
        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function encodingOptions(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'magnet')) {
            $result[] = 'magstripe';
        }
        if (str_contains($fold, 'kontaktchip') || str_contains($fold, 'contactchip')) {
            $result[] = 'contact_chip';
        }
        if (str_contains($fold, 'kontaktlos') || str_contains($fold, 'rfid') || str_contains($fold, 'nfc') || str_contains($fold, 'mifare')) {
            $result[] = 'contactless';
        }
        return array_values(array_unique($result));
    }

    /** @return list<string>|null */
    private function resolution(string $value): ?array
    {
        foreach ([1200, 600, 300] as $dpi) {
            if (preg_match('/(^|\D)'.$dpi.'\s*dpi\b/iu', $value)) {
                return ['dpi_'.$dpi];
            }
        }
        return null;
    }

    /** @return list<string>|null */
    private function ipRating(string $value): ?array
    {
        if (preg_match('/\bIP\s*(30|40|42|54|65|67)\b/iu', $value, $m)) {
            return ['ip'.$m[1]];
        }
        return null;
    }

    private function chipName(string $value): ?string
    {
        $patterns = [
            '/\bMIFARE\s+DESFire\s+(?:EV[123]\s+)?(?:2K|4K|8K)?\b/iu',
            '/\bMIFARE\s+Classic\s+(?:1K|4K)(?:\s+EV1)?\b/iu',
            '/\bMIFARE\s+(?:1K|4K)\b/iu',
            '/\bNTAG\s*\d{3}\b/iu',
            '/\bI[- ]?CODE\s+(?:SLI|SL2)\b/iu',
            '/\bEM\s*4102\b/iu',
            '/\bEM\s*4200\b/iu',
            '/\bTK\s*4102\b/iu',
            '/\bHitag\s*[12S]?\b/iu',
            '/\bAlien\s+Higgs\s*\d?\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $m)) {
                return trim(preg_replace('/\s+/', ' ', $m[0]) ?? $m[0]);
            }
        }
        return null;
    }

    /** @param list<string> $units */
    private function quantityNearUnit(string $value, array $units): ?int
    {
        $unitPattern = implode('|', $units);
        if (!preg_match('/\b(\d{1,3}(?:[.\s]\d{3})+|\d{2,5})\s*(?:'.$unitPattern.')\b/iu', $value, $m)) {
            return null;
        }
        $number = preg_replace('/\D/', '', $m[1]) ?? '';
        return $number !== '' ? (int) $number : null;
    }

    private function hasTaxon(LegacyProductRecord $record, string $code): bool
    {
        return in_array($code, $record->taxonCodes, true);
    }

    private function hasTaxonPrefix(LegacyProductRecord $record, string $prefix): bool
    {
        foreach ($record->taxonCodes as $code) {
            if ($code === $prefix || str_starts_with($code, $prefix.'_')) {
                return true;
            }
        }
        return false;
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/<br\s*\/?>/iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    public static function fold(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}
