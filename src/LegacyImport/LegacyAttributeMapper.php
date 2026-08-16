<?php

declare(strict_types=1);

namespace App\LegacyImport;

final class LegacyAttributeMapper
{
    /**
     * @param array<string,string> $legacy
     * @param array<string,bool> $vocabulary
     * @return array{attributes:array<string,mixed>,model:string,unknown:list<string>}
     */
    public function map(array $legacy, array $vocabulary): array
    {
        /** @var array<string,string|int|list<string>> $mapped */
        $mapped = []; $model = ''; $unknown = [];
        foreach ($legacy as $name => $value) {
            $key = self::fold($name);
            if (!isset($vocabulary[$key])) { $unknown[] = $name; continue; }
            if ($key === 'modell') { $model = $value; continue; }
            $code = match ($key) {
                'kapazitat' => 'CN_RIBBON_YIELD',
                'frequenz' => 'CN_RFID_FREQUENCY',
                'technologie' => 'CN_RFID_TECHNOLOGIES',
                'anschluss' => 'CN_CONNECTIVITY',
                'druckverfahren' => 'CN_PRINTER_TECHNOLOGY',
                'druckgeschwindigkeitfarbe', 'druckgeschwindigkeitmonochrom' => 'CN_PRINT_SPEED',
                'druckauflosung', 'auflosung' => 'CN_PRINT_RESOLUTION',
                'karteneinzug', 'kapazitatkarteneinzug' => 'CN_CARD_INPUT_CAPACITY',
                'kartenausgabe', 'kapazitatkartenausgabe' => 'CN_CARD_OUTPUT_CAPACITY',
                'kartenstarke' => 'CN_CARD_THICKNESS',
                'kartentyp' => 'CN_CARD_MATERIAL',
                'lesereichweite' => 'CN_RFID_READ_RANGE',
                'farbe' => 'CN_PRODUCT_COLOR',
                // There is no final generic dimensions/weight product attribute;
                // retain them in the report rather than writing to the wrong field.
                default => null,
            };
            if ($code === null) { $unknown[] = $name; continue; }
            $normal = $this->normalizeValue($code, $value);
            if ($normal === null) { $unknown[] = $name.':'.$value; continue; }
            if ($code === 'CN_PRINT_SPEED' && isset($mapped[$code]) && is_string($mapped[$code])) { $mapped[$code] .= '; '.$value; }
            else { $mapped[$code] = $normal; }
        }
        return ['attributes'=>$mapped, 'model'=>$model, 'unknown'=>array_values(array_unique($unknown))];
    }

    private function normalizeValue(string $code, string $value): mixed
    {
        $fold = self::fold($value);
        return match ($code) {
            'CN_RFID_FREQUENCY' => array_values(array_filter([
                str_contains($fold, '125khz') || str_contains($fold, '1342khz') ? 'lf_125' : null,
                str_contains($fold, '1356mhz') ? 'hf_1356' : null,
                str_contains($fold, '860960mhz') ? 'uhf' : null,
            ])),
            'CN_RFID_TECHNOLOGIES' => array_values(array_filter([
                str_contains($fold, 'mifareclassic') || str_contains($fold, 'mifareclassik') ? 'mifare_classic' : null,
                str_contains($fold, 'desfire') ? 'mifare_desfire' : null,
                str_contains($fold, 'ntag') || str_contains($fold, 'nfc') ? 'ntag' : null,
                str_contains($fold, 'legic') ? 'legic' : null,
                str_contains($fold, 'iclass') ? 'hid_iclass' : null,
                str_contains($fold, 'em4102') ? 'em4102' : null,
            ])),
            'CN_CONNECTIVITY' => array_values(array_filter([
                str_contains($fold, 'usb') ? 'usb' : null, str_contains($fold, 'rs232') ? 'serial' : null,
                str_contains($fold, 'ethernet') ? 'ethernet' : null,
            ])),
            'CN_PRINTER_TECHNOLOGY' => str_contains($fold, 'retransfer') ? 'retransfer' : (str_contains($fold, 'sublimation') || str_contains($fold, 'thermo') ? 'direct_to_card' : null),
            'CN_PRINT_RESOLUTION' => preg_match('/(^|\D)1200\s*dpi/i', $value) ? 'dpi_1200' : (preg_match('/(^|\D)600\s*dpi/i', $value) ? 'dpi_600' : (preg_match('/(^|\D)300\s*dpi/i', $value) ? 'dpi_300' : null)),
            'CN_CARD_INPUT_CAPACITY', 'CN_CARD_OUTPUT_CAPACITY', 'CN_RIBBON_YIELD' => preg_match('/\d+/', $value, $m) ? (int)$m[0] : null,
            'CN_CARD_MATERIAL' => str_contains($fold, 'pvc') ? 'pvc' : (str_contains($fold, 'petg') ? 'petg' : (str_contains($fold, 'polycarbonat') ? 'polycarbonate' : (str_contains($fold, 'abs') ? 'abs' : null))),
            'CN_PRODUCT_COLOR' => array_values(array_filter([str_contains($fold, 'weiss') ? 'white' : null, str_contains($fold, 'schwarz') ? 'black' : null])),
            default => $value,
        };
    }

    public static function fold(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), ['ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'ss']);
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}
