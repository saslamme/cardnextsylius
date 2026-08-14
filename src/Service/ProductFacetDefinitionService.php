<?php

declare(strict_types=1);

namespace App\Service;

final class ProductFacetDefinitionService
{
    /**
     * @var array<string, list<array{
     *     name: string,
     *     label: string,
     *     type: 'select'|'boolean',
     *     attribute: string,
     *     choices?: array<string, string>
     * }>>
     */
    private const PROFILES = [
        'CARD_PRINTERS' => [
            [
                'name' => 'printer_technology',
                'label' => 'Drucktechnologie',
                'type' => 'select',
                'attribute' => 'CN_PRINTER_TECHNOLOGY',
                'choices' => [
                    'Direct-to-Card' => 'direct_to_card',
                    'Retransfer' => 'retransfer',
                ],
            ],
            [
                'name' => 'print_sides',
                'label' => 'Druckseiten',
                'type' => 'select',
                'attribute' => 'CN_PRINT_SIDES',
                'choices' => [
                    'Einseitig' => 'single',
                    'Beidseitig / Duplex' => 'duplex',
                ],
            ],
            [
                'name' => 'print_resolution',
                'label' => 'Druckauflösung',
                'type' => 'select',
                'attribute' => 'CN_PRINT_RESOLUTION',
                'choices' => [
                    '300 dpi' => '300',
                    '600 dpi' => '600',
                    '1200 dpi' => '1200',
                ],
            ],
            [
                'name' => 'connectivity',
                'label' => 'Schnittstellen',
                'type' => 'select',
                'attribute' => 'CN_CONNECTIVITY',
                'choices' => [
                    'USB' => 'usb',
                    'Ethernet' => 'ethernet',
                    'WLAN / Wi-Fi' => 'wifi',
                    'Bluetooth' => 'bluetooth',
                    'RS-232 / Seriell' => 'serial',
                ],
            ],
            [
                'name' => 'encoding_options',
                'label' => 'Kodieroptionen',
                'type' => 'select',
                'attribute' => 'CN_ENCODING_OPTIONS',
                'choices' => [
                    'Magnetstreifen' => 'magstripe',
                    'Kontaktchip' => 'contact_chip',
                    'Kontaktlos / RFID / NFC' => 'contactless',
                ],
            ],
        ],
        'RFID_READERS' => [
            [
                'name' => 'rfid_frequency',
                'label' => 'RFID-Frequenz',
                'type' => 'select',
                'attribute' => 'CN_RFID_FREQUENCY',
                'choices' => [
                    'LF 125 kHz' => 'lf_125',
                    'HF 13,56 MHz / NFC' => 'hf_1356',
                    'UHF 860–960 MHz' => 'uhf',
                ],
            ],
            [
                'name' => 'rfid_technology',
                'label' => 'RFID-Technologie',
                'type' => 'select',
                'attribute' => 'CN_RFID_TECHNOLOGIES',
                'choices' => [
                    'MIFARE Classic' => 'mifare_classic',
                    'MIFARE DESFire' => 'mifare_desfire',
                    'NTAG / NFC' => 'ntag',
                    'LEGIC' => 'legic',
                    'HID Prox' => 'hid_prox',
                    'HID iCLASS / Seos' => 'hid_iclass',
                    'EM4102 / EM4200' => 'em4102',
                    'FeliCa' => 'felica',
                ],
            ],
            [
                'name' => 'rfid_interface',
                'label' => 'Schnittstelle',
                'type' => 'select',
                'attribute' => 'CN_RFID_INTERFACE',
                'choices' => [
                    'USB' => 'usb',
                    'RS-232' => 'rs232',
                    'Wiegand' => 'wiegand',
                    'OSDP' => 'osdp',
                    'Ethernet' => 'ethernet',
                    'Bluetooth' => 'bluetooth',
                ],
            ],
            [
                'name' => 'rfid_form_factor',
                'label' => 'Bauform',
                'type' => 'select',
                'attribute' => 'CN_RFID_FORM_FACTOR',
                'choices' => [
                    'Desktop' => 'desktop',
                    'Aufbaumontage' => 'surface_mount',
                    'Einbaumontage / Panel Mount' => 'panel_mount',
                    'OEM / Embedded' => 'oem',
                ],
            ],
            [
                'name' => 'ip_rating',
                'label' => 'Schutzart',
                'type' => 'select',
                'attribute' => 'CN_IP_RATING',
                'choices' => self::IP_CHOICES,
            ],
        ],
        'PLASTIC_CARDS' => [
            [
                'name' => 'card_material',
                'label' => 'Kartenmaterial',
                'type' => 'select',
                'attribute' => 'CN_CARD_MATERIAL',
                'choices' => [
                    'PVC' => 'pvc',
                    'PET-G' => 'petg',
                    'ABS' => 'abs',
                    'Polycarbonat' => 'polycarbonate',
                    'Papier / Karton' => 'paper',
                ],
            ],
            [
                'name' => 'card_format',
                'label' => 'Kartenformat',
                'type' => 'select',
                'attribute' => 'CN_CARD_FORMAT',
                'choices' => [
                    'CR80 / ID-1' => 'cr80',
                    'CR79' => 'cr79',
                    'Weitere Formate' => 'other',
                ],
            ],
            [
                'name' => 'product_color',
                'label' => 'Farbe',
                'type' => 'select',
                'attribute' => 'CN_PRODUCT_COLOR',
                'choices' => self::COLOR_CHOICES,
            ],
            [
                'name' => 'magnetic_stripe',
                'label' => 'Magnetstreifen',
                'type' => 'select',
                'attribute' => 'CN_MAGNETIC_STRIPE',
                'choices' => [
                    'Ohne' => 'none',
                    'LoCo' => 'loco',
                    'HiCo' => 'hico',
                ],
            ],
            [
                'name' => 'rfid_frequency',
                'label' => 'RFID-Frequenz',
                'type' => 'select',
                'attribute' => 'CN_RFID_FREQUENCY',
                'choices' => [
                    'LF 125 kHz' => 'lf_125',
                    'HF 13,56 MHz / NFC' => 'hf_1356',
                    'UHF 860–960 MHz' => 'uhf',
                ],
            ],
        ],
        'CARD_ACCESSORIES' => [
            [
                'name' => 'accessory_type',
                'label' => 'Zubehörtyp',
                'type' => 'select',
                'attribute' => 'CN_ACCESSORY_TYPE',
                'choices' => [
                    'Kartenhalter' => 'holder',
                    'Lanyard' => 'lanyard',
                    'Clip' => 'clip',
                    'Jojo / Ausweishalter' => 'reel',
                    'Strap Clip' => 'strap',
                    'Sonstiges' => 'other',
                ],
            ],
            [
                'name' => 'card_orientation',
                'label' => 'Kartenausrichtung',
                'type' => 'select',
                'attribute' => 'CN_CARD_ORIENTATION',
                'choices' => [
                    'Hochformat' => 'portrait',
                    'Querformat' => 'landscape',
                    'Universal' => 'universal',
                ],
            ],
            [
                'name' => 'product_color',
                'label' => 'Farbe',
                'type' => 'select',
                'attribute' => 'CN_PRODUCT_COLOR',
                'choices' => self::COLOR_CHOICES,
            ],
        ],
        'RIBBONS' => [
            [
                'name' => 'ribbon_type',
                'label' => 'Farbbandtyp',
                'type' => 'select',
                'attribute' => 'CN_RIBBON_TYPE',
                'choices' => [
                    'YMCKO' => 'ymcko',
                    'YMCKOK' => 'ymckok',
                    'YMCK' => 'ymck',
                    'Monochrom' => 'monochrome',
                    'Overlay / Schutzfilm' => 'overlay',
                    'Retransfer-Film' => 'retransfer',
                    'Weitere' => 'other',
                ],
            ],
        ],
        'BARCODE_SCANNERS' => [
            [
                'name' => 'barcode_dimension',
                'label' => 'Barcode-Typ',
                'type' => 'select',
                'attribute' => 'CN_BARCODE_DIMENSION',
                'choices' => [
                    '1D' => '1d',
                    '2D' => '2d',
                    'DPM' => 'dpm',
                ],
            ],
            [
                'name' => 'scan_engine',
                'label' => 'Scan-Technologie',
                'type' => 'select',
                'attribute' => 'CN_SCAN_ENGINE',
                'choices' => [
                    'Laser' => 'laser',
                    'Linear Imager' => 'linear_imager',
                    'Area Imager' => 'area_imager',
                ],
            ],
            [
                'name' => 'scanner_interfaces',
                'label' => 'Schnittstellen',
                'type' => 'select',
                'attribute' => 'CN_SCANNER_INTERFACES',
                'choices' => [
                    'USB' => 'usb',
                    'RS-232' => 'rs232',
                    'Bluetooth' => 'bluetooth',
                    'WLAN / Wi-Fi' => 'wifi',
                ],
            ],
            [
                'name' => 'wireless',
                'label' => 'Kabellos',
                'type' => 'boolean',
                'attribute' => 'CN_WIRELESS',
            ],
            [
                'name' => 'ip_rating',
                'label' => 'Schutzart',
                'type' => 'select',
                'attribute' => 'CN_IP_RATING',
                'choices' => self::IP_CHOICES,
            ],
        ],
        'TIME_ATTENDANCE' => [
            [
                'name' => 'identification_methods',
                'label' => 'Identifikationsmethoden',
                'type' => 'select',
                'attribute' => 'CN_IDENTIFICATION_METHODS',
                'choices' => [
                    'RFID / Karte' => 'rfid',
                    'Fingerabdruck' => 'fingerprint',
                    'Gesichtserkennung' => 'face',
                    'PIN / Tastatur' => 'pin',
                    'Barcode / QR-Code' => 'barcode',
                ],
            ],
            [
                'name' => 'rfid_technology',
                'label' => 'RFID-Technologie',
                'type' => 'select',
                'attribute' => 'CN_RFID_TECHNOLOGIES',
                'choices' => [
                    'MIFARE Classic' => 'mifare_classic',
                    'MIFARE DESFire' => 'mifare_desfire',
                    'NTAG / NFC' => 'ntag',
                    'LEGIC' => 'legic',
                    'HID Prox' => 'hid_prox',
                    'HID iCLASS / Seos' => 'hid_iclass',
                ],
            ],
            [
                'name' => 'network_interfaces',
                'label' => 'Netzwerk',
                'type' => 'select',
                'attribute' => 'CN_NETWORK_INTERFACES',
                'choices' => self::NETWORK_CHOICES,
            ],
            [
                'name' => 'ip_rating',
                'label' => 'Schutzart',
                'type' => 'select',
                'attribute' => 'CN_IP_RATING',
                'choices' => self::IP_CHOICES,
            ],
            [
                'name' => 'offline_mode',
                'label' => 'Offline-Betrieb',
                'type' => 'boolean',
                'attribute' => 'CN_OFFLINE_MODE',
            ],
        ],
        'ACCESS_CONTROL' => [
            [
                'name' => 'access_component_type',
                'label' => 'Komponententyp',
                'type' => 'select',
                'attribute' => 'CN_ACCESS_COMPONENT_TYPE',
                'choices' => [
                    'Leser' => 'reader',
                    'Controller' => 'controller',
                    'Zutrittsterminal' => 'terminal',
                    'Türmodul' => 'door_module',
                    'Zubehör' => 'accessory',
                ],
            ],
            [
                'name' => 'access_protocols',
                'label' => 'Zutrittsprotokolle',
                'type' => 'select',
                'attribute' => 'CN_ACCESS_PROTOCOLS',
                'choices' => [
                    'OSDP' => 'osdp',
                    'Wiegand' => 'wiegand',
                    'RS-485' => 'rs485',
                    'TCP/IP' => 'tcpip',
                ],
            ],
            [
                'name' => 'rfid_technology',
                'label' => 'RFID-Technologie',
                'type' => 'select',
                'attribute' => 'CN_RFID_TECHNOLOGIES',
                'choices' => [
                    'MIFARE Classic' => 'mifare_classic',
                    'MIFARE DESFire' => 'mifare_desfire',
                    'NTAG / NFC' => 'ntag',
                    'LEGIC' => 'legic',
                    'HID Prox' => 'hid_prox',
                    'HID iCLASS / Seos' => 'hid_iclass',
                ],
            ],
            [
                'name' => 'network_interfaces',
                'label' => 'Netzwerk',
                'type' => 'select',
                'attribute' => 'CN_NETWORK_INTERFACES',
                'choices' => self::NETWORK_CHOICES,
            ],
            [
                'name' => 'ip_rating',
                'label' => 'Schutzart',
                'type' => 'select',
                'attribute' => 'CN_IP_RATING',
                'choices' => self::IP_CHOICES,
            ],
            [
                'name' => 'poe',
                'label' => 'PoE',
                'type' => 'boolean',
                'attribute' => 'CN_POE',
            ],
        ],
    ];

    private const IP_CHOICES = [
        'IP30' => 'ip30',
        'IP40' => 'ip40',
        'IP42' => 'ip42',
        'IP54' => 'ip54',
        'IP65' => 'ip65',
        'IP67' => 'ip67',
    ];

    private const COLOR_CHOICES = [
        'Schwarz' => 'black',
        'Weiß' => 'white',
        'Grau' => 'grey',
        'Silber' => 'silver',
        'Blau' => 'blue',
        'Rot' => 'red',
        'Transparent' => 'transparent',
        'Weitere' => 'other',
    ];

    private const NETWORK_CHOICES = [
        'Ethernet' => 'ethernet',
        'WLAN / Wi-Fi' => 'wifi',
        'PoE' => 'poe',
        'RS-485' => 'rs485',
        'LTE / Mobilfunk' => 'lte',
    ];

    /**
     * @return list<array{name: string, label: string, type: 'select'|'boolean', attribute: string, choices?: array<string, string>}>
     */
    public function forProfile(string $profileCode): array
    {
        return self::PROFILES[$profileCode] ?? [];
    }

    public function hasProfile(string $profileCode): bool
    {
        return isset(self::PROFILES[$profileCode]);
    }
}
