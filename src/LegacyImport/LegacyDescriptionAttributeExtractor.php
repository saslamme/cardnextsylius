<?php

declare(strict_types=1);

namespace App\LegacyImport;

final class LegacyDescriptionAttributeExtractor
{
    /** @return array<string, mixed> */
    public function extract(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $attributes = [];

        if (preg_match_all('#<(?:li|p|div)[^>]*>\s*<strong[^>]*>(.*?)</strong>\s*(.*?)</(?:li|p|div)>#isu', $html, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = $this->cleanText($match[1]);
                $value = $this->cleanText($match[2]);
                if ($label === '' || $value === '') {
                    continue;
                }

                $this->mapPair($attributes, $label, $value);
            }
        }

        $plain = $this->cleanText($html);
        $folded = self::fold($plain);

        if (!isset($attributes['CN_PRINT_MODE'])) {
            $modes = [];
            if (str_contains($folded, 'farbdruck') || str_contains($folded, 'vollfarb')) {
                $modes[] = 'color';
            }
            if (str_contains($folded, 'monochrom')) {
                $modes[] = 'monochrome';
            }
            $this->add($attributes, 'CN_PRINT_MODE', $modes);
        }

        if (!isset($attributes['CN_PRINT_SIDES'])) {
            $this->add($attributes, 'CN_PRINT_SIDES', $this->normalizePrintSides($plain));
        }

        if (!isset($attributes['CN_PRINTER_TECHNOLOGY'])) {
            $this->add($attributes, 'CN_PRINTER_TECHNOLOGY', $this->normalizePrinterTechnology($plain));
        }

        if (!isset($attributes['CN_CARD_FORMAT'])) {
            $this->add($attributes, 'CN_CARD_FORMAT', $this->normalizeCardFormat($plain));
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function mapPair(array &$attributes, string $label, string $value): void
    {
        $key = self::fold(rtrim($label, ':'));

        if (in_array($key, ['druckverfahren', 'drucktechnologie'], true)) {
            $this->add($attributes, 'CN_PRINTER_TECHNOLOGY', $this->normalizePrinterTechnology($value));
        }

        if (in_array($key, ['druckauflosung', 'auflosung', 'auflosungdruck'], true)) {
            $this->add($attributes, 'CN_PRINT_RESOLUTION', $this->normalizeResolution($value));
        }

        if (in_array($key, ['druck', 'druckmodus', 'druckseiten', 'simplexduplex'], true)) {
            $this->add($attributes, 'CN_PRINT_SIDES', $this->normalizePrintSides($value));
            $this->add($attributes, 'CN_PRINT_MODE', $this->normalizePrintMode($value));
        }

        if (str_starts_with($key, 'druckgeschwindigkeit')) {
            $this->add($attributes, 'CN_PRINT_SPEED', $value);
        }

        if (in_array($key, ['kartenformat', 'kartenformate', 'kartenformatestandard'], true)) {
            $this->add($attributes, 'CN_CARD_FORMAT', $this->normalizeCardFormat($value));
        }

        if (in_array($key, ['zufuhrkapazitat', 'kartenzufuhr', 'kartenzufuhrkapazitat', 'kapazitatkarteneinzug', 'karteneinzug'], true)) {
            $this->add($attributes, 'CN_CARD_INPUT_CAPACITY', $this->firstInteger($value));
        }

        if (in_array($key, ['ausgabekapazitat', 'auffangkapazitat', 'kartenausgabe', 'kartenausgabekapazitat', 'kapazitatkartenausgabe'], true)) {
            $this->add($attributes, 'CN_CARD_OUTPUT_CAPACITY', $this->firstInteger($value));
        }

        if (in_array($key, ['schnittstelle', 'schnittstellen', 'anschluss', 'anschlusse', 'interfaces'], true)) {
            $this->add($attributes, 'CN_CONNECTIVITY', $this->normalizeConnectivity($value, 'connectivity'));
            $this->add($attributes, 'CN_RFID_INTERFACE', $this->normalizeConnectivity($value, 'rfid'));
            $this->add($attributes, 'CN_SCANNER_INTERFACES', $this->normalizeConnectivity($value, 'scanner'));
            $this->add($attributes, 'CN_NETWORK_INTERFACES', $this->normalizeConnectivity($value, 'network'));
            $wireless = $this->normalizeConnectivity($value, 'scanner');
            if (array_intersect($wireless, ['bluetooth', 'wifi']) !== []) {
                $this->add($attributes, 'CN_WIRELESS', true);
            }
            if (in_array('poe', $this->normalizeConnectivity($value, 'network'), true)) {
                $this->add($attributes, 'CN_POE', true);
            }
        }

        if (in_array($key, ['kodierung', 'kodieroptionen', 'kodierungsoptionen', 'encoder', 'encoding'], true)) {
            $this->add($attributes, 'CN_ENCODING_OPTIONS', $this->normalizeEncoding($value));
        }

        if (in_array($key, ['display', 'displaybedienung', 'bedienung', 'anzeige'], true)) {
            $this->add($attributes, 'CN_PRINTER_DISPLAY', $value);
            $this->add($attributes, 'CN_TERMINAL_DISPLAY', $value);
        }

        if (in_array($key, ['frequenz', 'frequenzbereich', 'unterstutztefrequenzen', 'rfidfrequenz'], true)) {
            $this->add($attributes, 'CN_RFID_FREQUENCY', $this->normalizeFrequency($value));
        }

        if (in_array($key, ['technologie', 'unterstutztechnologien', 'unterstutztetechnologien', 'rfidnfcstandards', 'kontaktloseunterstutzung', 'kompatiblemedien', 'unterstutztestandards'], true)) {
            $this->add($attributes, 'CN_RFID_TECHNOLOGIES', $this->normalizeRfidTechnologies($value));
        }

        if (in_array($key, ['lesereichweite', 'leseabstand', 'reichweitelesen'], true)) {
            $this->add($attributes, 'CN_RFID_READ_RANGE', $value);
        }

        if (in_array($key, ['bauform', 'betriebsart', 'montage', 'montageart', 'gehauseform'], true)) {
            $this->add($attributes, 'CN_RFID_FORM_FACTOR', $this->normalizeFormFactor($value));
        }

        if (in_array($key, ['ausgabemodus', 'tastaturemulation', 'lesemodi', 'usbprotokoll', 'usbprotokolle'], true)) {
            $this->add($attributes, 'CN_RFID_OUTPUT_MODE', $this->normalizeOutputMode($label . ' ' . $value));
        }

        if (in_array($key, ['schutzart', 'iprating', 'ipklasse'], true)) {
            $this->add($attributes, 'CN_IP_RATING', $this->normalizeIpRating($value));
        }

        if (in_array($key, ['betriebstemperatur', 'temperaturbereich', 'betriebstemperaturbereich'], true)) {
            $this->add($attributes, 'CN_OPERATING_TEMPERATURE', $value);
        }

        if (in_array($key, ['material', 'produktmaterial', 'gehausermaterial', 'gehaeusematerial'], true)) {
            $this->add($attributes, 'CN_PRODUCT_MATERIAL', $value);
            $this->add($attributes, 'CN_CARD_MATERIAL', $this->normalizeCardMaterial($value));
        }

        if (in_array($key, ['kartentyp', 'kartentypen', 'kartenmaterial'], true)) {
            $this->add($attributes, 'CN_CARD_MATERIAL', $this->normalizeCardMaterial($value));
        }

        if (in_array($key, ['kartendicke', 'kartenstarke', 'kartenstaerke'], true)) {
            $this->add($attributes, 'CN_CARD_THICKNESS', $value);
        }

        if (in_array($key, ['farbe', 'produktfarbe', 'gehausfarbe', 'gehaeusefarbe'], true)) {
            $this->add($attributes, 'CN_PRODUCT_COLOR', $this->normalizeColor($value));
        }

        if (in_array($key, ['oberflache', 'kartenoberflache'], true)) {
            $this->add($attributes, 'CN_CARD_SURFACE', $this->normalizeSurface($value));
        }

        if (in_array($key, ['magnetstreifen', 'magnetspur'], true)) {
            $this->add($attributes, 'CN_MAGNETIC_STRIPE', $this->normalizeMagneticStripe($value));
        }

        if (in_array($key, ['chiptyp', 'chip', 'transponder', 'speicherkapazitat', 'standard'], true)) {
            $this->add($attributes, 'CN_CARD_CHIP', $label . ': ' . $value);
        }

        if (in_array($key, ['kartenausrichtung', 'ausrichtung', 'orientierung'], true)) {
            $this->add($attributes, 'CN_CARD_ORIENTATION', $this->normalizeOrientation($value));
        }

        if (in_array($key, ['kartenkapazitat', 'kapazitat'], true)) {
            $quantity = $this->firstInteger($value);
            $this->add($attributes, 'CN_CARD_CAPACITY', $quantity);
            $this->add($attributes, 'CN_RIBBON_YIELD', $quantity);
        }

        if (in_array($key, ['befestigung', 'befestigungsart', 'attachment'], true)) {
            $this->add($attributes, 'CN_ATTACHMENT_TYPE', $value);
        }

        if (in_array($key, ['farbbandtyp', 'farbtyp', 'bandtyp'], true)) {
            $this->add($attributes, 'CN_RIBBON_TYPE', $this->normalizeRibbonType($value));
            $this->add($attributes, 'CN_RIBBON_COLOR', $value);
        }

        if (in_array($key, ['druckkapazitat', 'reichweite', 'kapazitatreichweitekarten'], true)) {
            $this->add($attributes, 'CN_RIBBON_YIELD', $this->firstInteger($value));
        }

        if (in_array($key, ['barcodetyp', 'barcodetypen', 'barcodearten', 'scantyp'], true)) {
            $this->add($attributes, 'CN_BARCODE_DIMENSION', $this->normalizeBarcodeDimension($value));
        }

        if (in_array($key, ['scantechnologie', 'scantechnik', 'scanengine'], true)) {
            $this->add($attributes, 'CN_SCAN_ENGINE', $this->normalizeScanEngine($value));
        }

        if (in_array($key, ['funkreichweite', 'wirelessrange'], true)) {
            $this->add($attributes, 'CN_WIRELESS_RANGE', $value);
        }

        if (in_array($key, ['sturzfestigkeit', 'dropresistance'], true)) {
            $this->add($attributes, 'CN_DROP_RESISTANCE', $value);
        }

        if (in_array($key, ['unterstutztecodes', 'unterstutztebarcodes', 'symbologien', 'barcodes'], true)) {
            $this->add($attributes, 'CN_SUPPORTED_SYMBOLOGIES', $value);
        }

        if (in_array($key, ['kabellos', 'wireless'], true)) {
            $this->add($attributes, 'CN_WIRELESS', $this->normalizeBoolean($value));
        }

        if (in_array($key, ['betriebssysteme', 'unterstutztebetriebssysteme', 'kompatiblebetriebssysteme', 'betriebssystemsupport', 'softwarekompatibilitat'], true)) {
            $this->add($attributes, 'CN_SUPPORTED_OS', $value);
        }

        if (in_array($key, ['lizenztyp', 'lizenz', 'lizenzmodell'], true)) {
            $this->add($attributes, 'CN_SOFTWARE_LICENSE', $value);
        }

        if (in_array($key, ['bereitstellung', 'lieferart', 'auslieferung'], true)) {
            $this->add($attributes, 'CN_SOFTWARE_DELIVERY', $value);
        }

        if (in_array($key, ['stromversorgung', 'spannungsversorgung', 'power'], true)) {
            $this->add($attributes, 'CN_POWER_SUPPLY', $value);
        }

        if (in_array($key, ['offlinebetrieb', 'offlinemodus'], true)) {
            $this->add($attributes, 'CN_OFFLINE_MODE', $this->normalizeBoolean($value));
        }

        if (in_array($key, ['identifikationsmethoden', 'identifikation'], true)) {
            $this->add($attributes, 'CN_IDENTIFICATION_METHODS', $this->normalizeIdentificationMethods($value));
        }

        if (in_array($key, ['protokolle', 'zutrittsprotokolle', 'kommunikationsprotokolle'], true)) {
            $this->add($attributes, 'CN_ACCESS_PROTOCOLS', $this->normalizeAccessProtocols($value));
        }

        if (in_array($key, ['anzahlturen', 'turen', 'tueren'], true)) {
            $this->add($attributes, 'CN_NUMBER_OF_DOORS', $this->firstInteger($value));
        }

        if ($key === 'poe') {
            $this->add($attributes, 'CN_POE', $this->normalizeBoolean($value));
        }

        if (in_array($key, ['relaisausgange', 'relaisausgaenge', 'relais'], true)) {
            $this->add($attributes, 'CN_RELAY_OUTPUTS', $this->firstInteger($value));
        }

        if (in_array($key, ['komponententyp', 'geratetyp', 'geraetetyp'], true)) {
            $this->add($attributes, 'CN_ACCESS_COMPONENT_TYPE', $this->normalizeAccessComponentType($value));
        }

        if (in_array($key, ['garantie', 'garantiezeit', 'garantiemonate'], true)) {
            $this->add($attributes, 'CN_WARRANTY_MONTHS', $this->normalizeWarrantyMonths($value));
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

        $current = $attributes[$code];
        if (is_array($current) && is_array($value)) {
            $strings = array_filter([...$current, ...$value], 'is_string');
            $attributes[$code] = array_values(array_unique($strings));

            return;
        }

        if (is_string($current) && is_string($value) && $current !== $value) {
            $parts = array_values(array_unique(array_filter([trim($current), trim($value)])));
            $attributes[$code] = implode('; ', $parts);
        }
    }

    /** @return list<string>|null */
    private function normalizePrinterTechnology(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'retransfer') || str_contains($fold, 'reversetransfer')) {
            return ['retransfer'];
        }
        if (str_contains($fold, 'direktkartendruck') || str_contains($fold, 'directtocard') || str_contains($fold, 'dyesublimation') || str_contains($fold, 'thermotransfer')) {
            return ['direct_to_card'];
        }

        return null;
    }

    /** @return list<string>|null */
    private function normalizeResolution(string $value): ?array
    {
        if (preg_match('/(^|\D)1200\s*dpi/i', $value)) {
            return ['dpi_1200'];
        }
        if (preg_match('/(^|\D)600\s*dpi/i', $value)) {
            return ['dpi_600'];
        }
        if (preg_match('/(^|\D)300\s*dpi/i', $value)) {
            return ['dpi_300'];
        }

        return null;
    }

    /** @return list<string>|null */
    private function normalizePrintSides(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'beidseit') || str_contains($fold, 'duplex')) {
            return ['duplex'];
        }
        if (str_contains($fold, 'einseit') || str_contains($fold, 'simplex')) {
            return ['single'];
        }

        return null;
    }

    /** @return list<string> */
    private function normalizePrintMode(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'farb') || str_contains($fold, 'color') || str_contains($fold, 'colour')) {
            $result[] = 'color';
        }
        if (str_contains($fold, 'monochrom') || str_contains($fold, 'schwarzweiss')) {
            $result[] = 'monochrome';
        }

        return $result;
    }

    /** @return list<string> */
    private function normalizeCardFormat(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'cr80') || str_contains($fold, 'id1') || (str_contains($fold, '856') && str_contains($fold, '54'))) {
            $result[] = 'cr80';
        }
        if (str_contains($fold, 'cr79')) {
            $result[] = 'cr79';
        }

        return $result;
    }

    /** @return list<string> */
    private function normalizeConnectivity(string $value, string $target): array
    {
        $value = preg_replace('/\([^)]*(?:optional|modellabh[aä]ngig)[^)]*\)/iu', '', $value) ?? $value;
        $fold = self::fold($value);
        $result = [];
        $map = match ($target) {
            'rfid' => ['usb' => 'usb', 'rs232' => 'rs232', 'seriell' => 'rs232', 'wiegand' => 'wiegand', 'osdp' => 'osdp', 'ethernet' => 'ethernet', 'bluetooth' => 'bluetooth'],
            'scanner' => ['usb' => 'usb', 'rs232' => 'rs232', 'seriell' => 'rs232', 'bluetooth' => 'bluetooth', 'wifi' => 'wifi', 'wlan' => 'wifi'],
            'network' => ['ethernet' => 'ethernet', 'wifi' => 'wifi', 'wlan' => 'wifi', 'poe' => 'poe', 'rs485' => 'rs485', 'lte' => 'lte'],
            default => ['usb' => 'usb', 'ethernet' => 'ethernet', 'wifi' => 'wifi', 'wlan' => 'wifi', 'bluetooth' => 'bluetooth', 'rs232' => 'serial', 'seriell' => 'serial'],
        };
        foreach ($map as $needle => $choice) {
            if (str_contains($fold, $needle)) {
                $result[] = $choice;
            }
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function normalizeEncoding(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'magnet')) {
            $result[] = 'magstripe';
        }
        if (str_contains($fold, 'kontaktchip') || str_contains($fold, 'contactchip') || str_contains($fold, 'smartcardkontakt')) {
            $result[] = 'contact_chip';
        }
        if (str_contains($fold, 'kontaktlos') || str_contains($fold, 'rfid') || str_contains($fold, 'nfc') || str_contains($fold, 'mifare')) {
            $result[] = 'contactless';
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function normalizeFrequency(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, '125khz') || str_contains($fold, '1342khz') || str_contains($fold, 'lf')) {
            $result[] = 'lf_125';
        }
        if (str_contains($fold, '1356mhz') || str_contains($fold, 'hf') || str_contains($fold, 'nfc')) {
            $result[] = 'hf_1356';
        }
        if (str_contains($fold, '860960mhz') || str_contains($fold, '865') || str_contains($fold, '868') || str_contains($fold, '915') || str_contains($fold, 'uhf')) {
            $result[] = 'uhf';
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function normalizeRfidTechnologies(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'mifareclassic') || str_contains($fold, 'mifareclassik')) {
            $result[] = 'mifare_classic';
        }
        if (str_contains($fold, 'desfire')) {
            $result[] = 'mifare_desfire';
        }
        if (str_contains($fold, 'ntag') || str_contains($fold, 'nfc')) {
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

    /** @return list<string>|null */
    private function normalizeFormFactor(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'oem') || str_contains($fold, 'embedded')) {
            return ['oem'];
        }
        if (str_contains($fold, 'panel') || str_contains($fold, 'einbau')) {
            return ['panel_mount'];
        }
        if (str_contains($fold, 'wand') || str_contains($fold, 'aufbau') || str_contains($fold, 'surface')) {
            return ['surface_mount'];
        }
        if (str_contains($fold, 'desktop') || str_contains($fold, 'tisch')) {
            return ['desktop'];
        }

        return null;
    }

    /** @return list<string> */
    private function normalizeOutputMode(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'tastatur') || str_contains($fold, 'keyboard')) {
            $result[] = 'keyboard';
        }
        if (str_contains($fold, 'virtualcom') || str_contains($fold, 'vcom')) {
            $result[] = 'virtual_com';
        }
        if (str_contains($fold, 'sdk') || str_contains($fold, 'api')) {
            $result[] = 'sdk';
        }
        if (str_contains($fold, 'wiegand') || str_contains($fold, 'osdp')) {
            $result[] = 'wiegand_osdp';
        }

        return array_values(array_unique($result));
    }

    /** @return list<string>|null */
    private function normalizeIpRating(string $value): ?array
    {
        if (!preg_match('/\bIP\s*(30|40|42|54|65|67)\b/i', $value, $m)) {
            return null;
        }

        return ['ip' . strtolower($m[1])];
    }

    /** @return list<string>|null */
    private function normalizeCardMaterial(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'polycarbonat') || str_contains($fold, 'polycarbonate')) {
            return ['polycarbonate'];
        }
        if (str_contains($fold, 'petg')) {
            return ['petg'];
        }
        if (str_contains($fold, 'pvc')) {
            return ['pvc'];
        }
        if (str_contains($fold, 'abs')) {
            return ['abs'];
        }
        if (str_contains($fold, 'papier') || str_contains($fold, 'karton')) {
            return ['paper'];
        }

        return null;
    }

    /** @return list<string> */
    private function normalizeColor(string $value): array
    {
        $fold = self::fold($value);
        $map = ['schwarz' => 'black', 'black' => 'black', 'weiss' => 'white', 'white' => 'white', 'grau' => 'grey', 'grey' => 'grey', 'silber' => 'silver', 'silver' => 'silver', 'blau' => 'blue', 'blue' => 'blue', 'rot' => 'red', 'red' => 'red', 'transparent' => 'transparent'];
        $result = [];
        foreach ($map as $needle => $choice) {
            if (str_contains($fold, $needle)) {
                $result[] = $choice;
            }
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function normalizeSurface(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'glanz') || str_contains($fold, 'gloss')) {
            $result[] = 'glossy';
        }
        if (str_contains($fold, 'matt')) {
            $result[] = 'matte';
        }
        if (str_contains($fold, 'signatur') || str_contains($fold, 'unterschrift')) {
            $result[] = 'signature';
        }

        return array_values(array_unique($result));
    }

    /** @return list<string>|null */
    private function normalizeMagneticStripe(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'hico')) {
            return ['hico'];
        }
        if (str_contains($fold, 'loco')) {
            return ['loco'];
        }
        if (str_contains($fold, 'ohne') || str_contains($fold, 'kein')) {
            return ['none'];
        }

        return null;
    }

    /** @return list<string>|null */
    private function normalizeOrientation(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'hochformat') || str_contains($fold, 'portrait')) {
            return ['portrait'];
        }
        if (str_contains($fold, 'querformat') || str_contains($fold, 'landscape')) {
            return ['landscape'];
        }
        if (str_contains($fold, 'universal') || (str_contains($fold, 'hoch') && str_contains($fold, 'quer'))) {
            return ['universal'];
        }

        return null;
    }

    /** @return list<string>|null */
    private function normalizeRibbonType(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'ymckok')) {
            return ['ymckok'];
        }
        if (str_contains($fold, 'ymcko')) {
            return ['ymcko'];
        }
        if (str_contains($fold, 'ymck')) {
            return ['ymck'];
        }
        if (str_contains($fold, 'retransfer') || str_contains($fold, 'film')) {
            return ['retransfer'];
        }
        if (str_contains($fold, 'overlay') || str_contains($fold, 'schutzfilm')) {
            return ['overlay'];
        }
        if (str_contains($fold, 'monochrom') || preg_match('/(^|\W)K(\W|$)/i', $value)) {
            return ['monochrome'];
        }

        return null;
    }

    /** @return list<string> */
    private function normalizeBarcodeDimension(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, '1d')) {
            $result[] = '1d';
        }
        if (str_contains($fold, '2d') || str_contains($fold, 'qr')) {
            $result[] = '2d';
        }
        if (str_contains($fold, 'dpm')) {
            $result[] = 'dpm';
        }

        return array_values(array_unique($result));
    }

    /** @return list<string>|null */
    private function normalizeScanEngine(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'areaimager') || str_contains($fold, 'areaimage')) {
            return ['area_imager'];
        }
        if (str_contains($fold, 'linearimager') || str_contains($fold, 'linearimage')) {
            return ['linear_imager'];
        }
        if (str_contains($fold, 'laser')) {
            return ['laser'];
        }

        return null;
    }

    /** @return list<string> */
    private function normalizeIdentificationMethods(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'rfid') || str_contains($fold, 'karte')) {
            $result[] = 'rfid';
        }
        if (str_contains($fold, 'finger')) {
            $result[] = 'fingerprint';
        }
        if (str_contains($fold, 'gesicht') || str_contains($fold, 'face')) {
            $result[] = 'face';
        }
        if (str_contains($fold, 'pin') || str_contains($fold, 'tastatur')) {
            $result[] = 'pin';
        }
        if (str_contains($fold, 'barcode') || str_contains($fold, 'qr')) {
            $result[] = 'barcode';
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function normalizeAccessProtocols(string $value): array
    {
        $fold = self::fold($value);
        $result = [];
        if (str_contains($fold, 'osdp')) {
            $result[] = 'osdp';
        }
        if (str_contains($fold, 'wiegand')) {
            $result[] = 'wiegand';
        }
        if (str_contains($fold, 'rs485')) {
            $result[] = 'rs485';
        }
        if (str_contains($fold, 'tcpip') || str_contains($fold, 'ethernet')) {
            $result[] = 'tcpip';
        }

        return array_values(array_unique($result));
    }

    /** @return list<string>|null */
    private function normalizeAccessComponentType(string $value): ?array
    {
        $fold = self::fold($value);
        if (str_contains($fold, 'controller')) {
            return ['controller'];
        }
        if (str_contains($fold, 'turmodul') || str_contains($fold, 'doormodule')) {
            return ['door_module'];
        }
        if (str_contains($fold, 'terminal')) {
            return ['terminal'];
        }
        if (str_contains($fold, 'leser') || str_contains($fold, 'reader')) {
            return ['reader'];
        }
        if (str_contains($fold, 'zubehor') || str_contains($fold, 'accessory')) {
            return ['accessory'];
        }

        return null;
    }

    private function normalizeBoolean(string $value): ?bool
    {
        $fold = self::fold($value);
        if ($fold === '') {
            return null;
        }
        if (preg_match('/\b(nein|no|false|ohne|nicht)\b/i', $value)) {
            return false;
        }
        if (preg_match('/\b(ja|yes|true|unterst[uü]tzt|integriert|vorhanden)\b/iu', $value)) {
            return true;
        }

        return null;
    }

    private function normalizeWarrantyMonths(string $value): ?int
    {
        if (!preg_match('/\d+/', $value, $m)) {
            return null;
        }
        $n = (int) $m[0];
        $fold = self::fold($value);
        if (str_contains($fold, 'jahr')) {
            return $n * 12;
        }

        return $n;
    }

    private function firstInteger(string $value): ?int
    {
        if (!preg_match('/\d+/', $value, $m)) {
            return null;
        }

        return (int) $m[0];
    }

    private function cleanText(string $value): string
    {
        $value = preg_replace('#<br\s*/?>#iu', ' ', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value, " \t\n\r\0\x0B:-");
    }

    public static function fold(string $value): string
    {
        $value = trim($value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        $value = strtr($value, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}
