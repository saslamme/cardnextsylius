<?php

declare(strict_types=1);

namespace App\LegacyImport;

final class LegacyCategoryMapper
{
    /** @return list<string> */
    public function map(string $filename): array
    {
        $name = mb_strtolower($filename);
        $rules = [
            'kartendrucker' => ['card_printers'], 'farbbaender' => ['ribbons'],
            'pvc-karten' => ['plastic_cards_pvc'], 'magnetkarten' => ['plastic_cards_magnetic'],
            'chipkarten' => ['plastic_cards_chip'], 'rfid-karten' => ['plastic_cards_rfid', 'rfid_transponder_cards'],
            'rfid-keyfobs' => ['rfid_transponder_keyfobs'], 'kartenjojos' => ['id_accessories_reels'],
            'kartenhalter' => ['id_accessories_holders'], 'hartplastik' => ['id_accessories_sleeves_hard'],
            'weichplastik' => ['id_accessories_sleeves_soft'], 'lanyard' => ['id_accessories_lanyards'],
            'kartenclips' => ['id_accessories_clips'], 'oem-reader' => ['rfid_readers_oem'],
            'rfid-reader' => ['rfid_readers'], 'barcode' => ['barcode_scanners'],
            'reinigungsmaterial' => ['cleaning_material'], 'software' => ['card_software'],
            'zubehoer__ersatzteile' => ['card_printer_accessories'], 'sicherheit' => ['access_control'],
        ];
        $mapped = [];
        foreach ($rules as $needle => $codes) {
            if (str_contains($name, $needle)) {
                $mapped = [...$mapped, ...$codes];
            }
        }

        return array_values(array_unique($mapped));
    }
}
