<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Product;
use App\Entity\Product\ProductAttribute;
use App\Entity\Product\ProductAttributeValue;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Attribute\AttributeType\CheckboxAttributeType;
use Sylius\Component\Attribute\AttributeType\IntegerAttributeType;
use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Attribute\AttributeType\TextareaAttributeType;
use Sylius\Component\Attribute\Model\AttributeValueInterface;

final readonly class ProductAttributeProfileService
{
    private const LOCALE = 'de_DE';

    /**
     * Common values which are useful across multiple Cardnext product families.
     *
     * @var list<string>
     */
    private const COMMON = [
        'CN_MPN',
        'CN_EAN',
        'CN_WARRANTY_MONTHS',
    ];

    /**
     * Taxon code => attribute codes.
     *
     * These taxon codes are the stable codes created by the Cardnext taxonomy setup.
     *
     * @var array<string, list<string>>
     */
    private const PROFILES = [
        'CARD_PRINTERS' => [
            'CN_PRINTER_TECHNOLOGY',
            'CN_PRINT_SIDES',
            'CN_PRINT_RESOLUTION',
            'CN_PRINT_MODE',
            'CN_PRINT_SPEED',
            'CN_CARD_FORMAT',
            'CN_CARD_INPUT_CAPACITY',
            'CN_CARD_OUTPUT_CAPACITY',
            'CN_CONNECTIVITY',
            'CN_ENCODING_OPTIONS',
            'CN_PRINTER_DISPLAY',
        ],
        'RFID_READERS' => [
            'CN_RFID_FREQUENCY',
            'CN_RFID_TECHNOLOGIES',
            'CN_RFID_INTERFACE',
            'CN_RFID_FORM_FACTOR',
            'CN_RFID_READ_RANGE',
            'CN_RFID_OUTPUT_MODE',
            'CN_IP_RATING',
            'CN_OPERATING_TEMPERATURE',
        ],
        'PLASTIC_CARDS' => [
            'CN_CARD_MATERIAL',
            'CN_CARD_FORMAT',
            'CN_CARD_THICKNESS',
            'CN_PRODUCT_COLOR',
            'CN_CARD_SURFACE',
            'CN_MAGNETIC_STRIPE',
            'CN_RFID_FREQUENCY',
            'CN_CARD_CHIP',
        ],
        'CARD_ACCESSORIES' => [
            'CN_ACCESSORY_TYPE',
            'CN_CARD_ORIENTATION',
            'CN_CARD_CAPACITY',
            'CN_PRODUCT_MATERIAL',
            'CN_PRODUCT_COLOR',
            'CN_ATTACHMENT_TYPE',
        ],
        'RIBBONS' => [
            'CN_RIBBON_TYPE',
            'CN_RIBBON_COLOR',
            'CN_RIBBON_YIELD',
            'CN_COMPATIBLE_PRINTERS',
        ],
        'BARCODE_SCANNERS' => [
            'CN_BARCODE_DIMENSION',
            'CN_SCAN_ENGINE',
            'CN_SCANNER_INTERFACES',
            'CN_WIRELESS',
            'CN_WIRELESS_RANGE',
            'CN_IP_RATING',
            'CN_DROP_RESISTANCE',
            'CN_SUPPORTED_SYMBOLOGIES',
        ],
        'TIME_ATTENDANCE' => [
            'CN_IDENTIFICATION_METHODS',
            'CN_RFID_TECHNOLOGIES',
            'CN_TERMINAL_DISPLAY',
            'CN_NETWORK_INTERFACES',
            'CN_IP_RATING',
            'CN_POWER_SUPPLY',
            'CN_OFFLINE_MODE',
            'CN_OPERATING_TEMPERATURE',
        ],
        'ACCESS_CONTROL' => [
            'CN_ACCESS_COMPONENT_TYPE',
            'CN_ACCESS_PROTOCOLS',
            'CN_RFID_TECHNOLOGIES',
            'CN_NETWORK_INTERFACES',
            'CN_IP_RATING',
            'CN_NUMBER_OF_DOORS',
            'CN_POE',
            'CN_RELAY_OUTPUTS',
            'CN_OPERATING_TEMPERATURE',
        ],
    ];

    /**
     * @var array<string, array{
     *     name: string,
     *     type: string,
     *     storage: string,
     *     position: int,
     *     configuration?: array<string, mixed>
     * }>
     */
    private const DEFINITIONS = [
        // ---------------------------------------------------------------------
        // Common
        // ---------------------------------------------------------------------
        'CN_MPN' => [
            'name' => 'Hersteller-Artikelnummer',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 100,
        ],
        'CN_EAN' => [
            'name' => 'EAN / GTIN',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 110,
        ],
        'CN_WARRANTY_MONTHS' => [
            'name' => 'Garantie (Monate)',
            'type' => IntegerAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_INTEGER,
            'position' => 120,
        ],

        // ---------------------------------------------------------------------
        // Shared technical facets
        // ---------------------------------------------------------------------
        'CN_CONNECTIVITY' => [
            'name' => 'Schnittstellen',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 210,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'usb' => ['de_DE' => 'USB', 'en_US' => 'USB'],
                    'ethernet' => ['de_DE' => 'Ethernet', 'en_US' => 'Ethernet'],
                    'wifi' => ['de_DE' => 'WLAN / Wi-Fi', 'en_US' => 'Wi-Fi'],
                    'bluetooth' => ['de_DE' => 'Bluetooth', 'en_US' => 'Bluetooth'],
                    'serial' => ['de_DE' => 'RS-232 / Seriell', 'en_US' => 'RS-232 / Serial'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_RFID_FREQUENCY' => [
            'name' => 'RFID-Frequenz',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 310,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'lf_125' => ['de_DE' => 'LF 125 kHz', 'en_US' => 'LF 125 kHz'],
                    'hf_1356' => ['de_DE' => 'HF 13,56 MHz / NFC', 'en_US' => 'HF 13.56 MHz / NFC'],
                    'uhf' => ['de_DE' => 'UHF 860–960 MHz', 'en_US' => 'UHF 860–960 MHz'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_RFID_TECHNOLOGIES' => [
            'name' => 'RFID-Technologien',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 320,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'mifare_classic' => ['de_DE' => 'MIFARE Classic', 'en_US' => 'MIFARE Classic'],
                    'mifare_desfire' => ['de_DE' => 'MIFARE DESFire', 'en_US' => 'MIFARE DESFire'],
                    'ntag' => ['de_DE' => 'NTAG / NFC', 'en_US' => 'NTAG / NFC'],
                    'legic' => ['de_DE' => 'LEGIC', 'en_US' => 'LEGIC'],
                    'hid_prox' => ['de_DE' => 'HID Prox', 'en_US' => 'HID Prox'],
                    'hid_iclass' => ['de_DE' => 'HID iCLASS / Seos', 'en_US' => 'HID iCLASS / Seos'],
                    'em4102' => ['de_DE' => 'EM4102 / EM4200', 'en_US' => 'EM4102 / EM4200'],
                    'felica' => ['de_DE' => 'FeliCa', 'en_US' => 'FeliCa'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_IP_RATING' => [
            'name' => 'Schutzart',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 390,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'ip30' => ['de_DE' => 'IP30', 'en_US' => 'IP30'],
                    'ip40' => ['de_DE' => 'IP40', 'en_US' => 'IP40'],
                    'ip42' => ['de_DE' => 'IP42', 'en_US' => 'IP42'],
                    'ip54' => ['de_DE' => 'IP54', 'en_US' => 'IP54'],
                    'ip65' => ['de_DE' => 'IP65', 'en_US' => 'IP65'],
                    'ip67' => ['de_DE' => 'IP67', 'en_US' => 'IP67'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_OPERATING_TEMPERATURE' => [
            'name' => 'Betriebstemperatur',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 395,
        ],
        'CN_PRODUCT_COLOR' => [
            'name' => 'Farbe',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 450,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'black' => ['de_DE' => 'Schwarz', 'en_US' => 'Black'],
                    'white' => ['de_DE' => 'Weiß', 'en_US' => 'White'],
                    'grey' => ['de_DE' => 'Grau', 'en_US' => 'Grey'],
                    'silver' => ['de_DE' => 'Silber', 'en_US' => 'Silver'],
                    'blue' => ['de_DE' => 'Blau', 'en_US' => 'Blue'],
                    'red' => ['de_DE' => 'Rot', 'en_US' => 'Red'],
                    'transparent' => ['de_DE' => 'Transparent', 'en_US' => 'Transparent'],
                    'other' => ['de_DE' => 'Weitere', 'en_US' => 'Other'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_PRODUCT_MATERIAL' => [
            'name' => 'Material',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 460,
        ],
        'CN_NETWORK_INTERFACES' => [
            'name' => 'Netzwerkschnittstellen',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 720,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'ethernet' => ['de_DE' => 'Ethernet', 'en_US' => 'Ethernet'],
                    'wifi' => ['de_DE' => 'WLAN / Wi-Fi', 'en_US' => 'Wi-Fi'],
                    'poe' => ['de_DE' => 'PoE', 'en_US' => 'PoE'],
                    'rs485' => ['de_DE' => 'RS-485', 'en_US' => 'RS-485'],
                    'lte' => ['de_DE' => 'LTE / Mobilfunk', 'en_US' => 'LTE / Cellular'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],

        // ---------------------------------------------------------------------
        // Card printers
        // ---------------------------------------------------------------------
        'CN_PRINTER_TECHNOLOGY' => [
            'name' => 'Drucktechnologie',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 200,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'direct_to_card' => ['de_DE' => 'Direct-to-Card', 'en_US' => 'Direct-to-Card'],
                    'retransfer' => ['de_DE' => 'Retransfer', 'en_US' => 'Retransfer'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_PRINT_SIDES' => [
            'name' => 'Druckseiten',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 220,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'single' => ['de_DE' => 'Einseitig', 'en_US' => 'Single-sided'],
                    'duplex' => ['de_DE' => 'Beidseitig / Duplex', 'en_US' => 'Dual-sided / Duplex'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_PRINT_RESOLUTION' => [
            'name' => 'Druckauflösung',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 230,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    '300' => ['de_DE' => '300 dpi', 'en_US' => '300 dpi'],
                    '600' => ['de_DE' => '600 dpi', 'en_US' => '600 dpi'],
                    '1200' => ['de_DE' => '1200 dpi', 'en_US' => '1200 dpi'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_PRINT_MODE' => [
            'name' => 'Druckmodus',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 240,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'color' => ['de_DE' => 'Farbdruck', 'en_US' => 'Color'],
                    'monochrome' => ['de_DE' => 'Monochrom', 'en_US' => 'Monochrome'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_PRINT_SPEED' => [
            'name' => 'Druckgeschwindigkeit',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 250,
        ],
        'CN_CARD_FORMAT' => [
            'name' => 'Kartenformat',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 260,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'cr80' => ['de_DE' => 'CR80 / ID-1 (85,60 × 53,98 mm)', 'en_US' => 'CR80 / ID-1 (85.60 × 53.98 mm)'],
                    'cr79' => ['de_DE' => 'CR79', 'en_US' => 'CR79'],
                    'other' => ['de_DE' => 'Weitere Formate', 'en_US' => 'Other formats'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_CARD_INPUT_CAPACITY' => [
            'name' => 'Kartenzufuhr (Karten)',
            'type' => IntegerAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_INTEGER,
            'position' => 270,
        ],
        'CN_CARD_OUTPUT_CAPACITY' => [
            'name' => 'Kartenausgabe (Karten)',
            'type' => IntegerAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_INTEGER,
            'position' => 280,
        ],
        'CN_ENCODING_OPTIONS' => [
            'name' => 'Kodieroptionen',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 290,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'magstripe' => ['de_DE' => 'Magnetstreifen', 'en_US' => 'Magnetic stripe'],
                    'contact_chip' => ['de_DE' => 'Kontaktchip', 'en_US' => 'Contact smart card'],
                    'contactless' => ['de_DE' => 'Kontaktlos / RFID / NFC', 'en_US' => 'Contactless / RFID / NFC'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_PRINTER_DISPLAY' => [
            'name' => 'Display / Bedienung',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 300,
        ],

        // ---------------------------------------------------------------------
        // RFID readers
        // ---------------------------------------------------------------------
        'CN_RFID_INTERFACE' => [
            'name' => 'Leser-Schnittstelle',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 330,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'usb' => ['de_DE' => 'USB', 'en_US' => 'USB'],
                    'rs232' => ['de_DE' => 'RS-232', 'en_US' => 'RS-232'],
                    'wiegand' => ['de_DE' => 'Wiegand', 'en_US' => 'Wiegand'],
                    'osdp' => ['de_DE' => 'OSDP', 'en_US' => 'OSDP'],
                    'ethernet' => ['de_DE' => 'Ethernet', 'en_US' => 'Ethernet'],
                    'bluetooth' => ['de_DE' => 'Bluetooth', 'en_US' => 'Bluetooth'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_RFID_FORM_FACTOR' => [
            'name' => 'Bauform',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 340,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'desktop' => ['de_DE' => 'Desktop', 'en_US' => 'Desktop'],
                    'surface_mount' => ['de_DE' => 'Aufbaumontage', 'en_US' => 'Surface mount'],
                    'panel_mount' => ['de_DE' => 'Einbaumontage / Panel Mount', 'en_US' => 'Panel mount'],
                    'oem' => ['de_DE' => 'OEM / Embedded', 'en_US' => 'OEM / Embedded'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_RFID_READ_RANGE' => [
            'name' => 'Lesereichweite',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 350,
        ],
        'CN_RFID_OUTPUT_MODE' => [
            'name' => 'Ausgabemodus',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 360,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'keyboard' => ['de_DE' => 'Keyboard Wedge / Tastatur', 'en_US' => 'Keyboard Wedge'],
                    'virtual_com' => ['de_DE' => 'Virtual COM', 'en_US' => 'Virtual COM'],
                    'sdk' => ['de_DE' => 'SDK / API', 'en_US' => 'SDK / API'],
                    'wiegand_osdp' => ['de_DE' => 'Wiegand / OSDP', 'en_US' => 'Wiegand / OSDP'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],

        // ---------------------------------------------------------------------
        // Plastic cards
        // ---------------------------------------------------------------------
        'CN_CARD_MATERIAL' => [
            'name' => 'Kartenmaterial',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 410,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'pvc' => ['de_DE' => 'PVC', 'en_US' => 'PVC'],
                    'petg' => ['de_DE' => 'PET-G', 'en_US' => 'PET-G'],
                    'abs' => ['de_DE' => 'ABS', 'en_US' => 'ABS'],
                    'polycarbonate' => ['de_DE' => 'Polycarbonat', 'en_US' => 'Polycarbonate'],
                    'paper' => ['de_DE' => 'Papier / Karton', 'en_US' => 'Paper / Cardboard'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_CARD_THICKNESS' => [
            'name' => 'Kartenstärke',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 420,
        ],
        'CN_CARD_SURFACE' => [
            'name' => 'Oberfläche',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 430,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'glossy' => ['de_DE' => 'Glänzend', 'en_US' => 'Glossy'],
                    'matte' => ['de_DE' => 'Matt', 'en_US' => 'Matte'],
                    'signature' => ['de_DE' => 'Signaturfeld', 'en_US' => 'Signature panel'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_MAGNETIC_STRIPE' => [
            'name' => 'Magnetstreifen',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 440,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'none' => ['de_DE' => 'Ohne', 'en_US' => 'None'],
                    'loco' => ['de_DE' => 'LoCo', 'en_US' => 'LoCo'],
                    'hico' => ['de_DE' => 'HiCo', 'en_US' => 'HiCo'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_CARD_CHIP' => [
            'name' => 'Chip / Transponder',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 470,
        ],

        // ---------------------------------------------------------------------
        // Card accessories
        // ---------------------------------------------------------------------
        'CN_ACCESSORY_TYPE' => [
            'name' => 'Zubehörtyp',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 500,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'holder' => ['de_DE' => 'Kartenhalter', 'en_US' => 'Card holder'],
                    'lanyard' => ['de_DE' => 'Lanyard', 'en_US' => 'Lanyard'],
                    'clip' => ['de_DE' => 'Clip', 'en_US' => 'Clip'],
                    'reel' => ['de_DE' => 'Jojo / Ausweishalter', 'en_US' => 'Badge reel'],
                    'strap' => ['de_DE' => 'Strap Clip', 'en_US' => 'Strap clip'],
                    'other' => ['de_DE' => 'Sonstiges', 'en_US' => 'Other'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_CARD_ORIENTATION' => [
            'name' => 'Kartenausrichtung',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 510,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'portrait' => ['de_DE' => 'Hochformat', 'en_US' => 'Portrait'],
                    'landscape' => ['de_DE' => 'Querformat', 'en_US' => 'Landscape'],
                    'universal' => ['de_DE' => 'Universal', 'en_US' => 'Universal'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_CARD_CAPACITY' => [
            'name' => 'Kartenkapazität',
            'type' => IntegerAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_INTEGER,
            'position' => 520,
        ],
        'CN_ATTACHMENT_TYPE' => [
            'name' => 'Befestigung',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 530,
        ],

        // ---------------------------------------------------------------------
        // Ribbons
        // ---------------------------------------------------------------------
        'CN_RIBBON_TYPE' => [
            'name' => 'Farbbandtyp',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 560,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'ymcko' => ['de_DE' => 'YMCKO', 'en_US' => 'YMCKO'],
                    'ymckok' => ['de_DE' => 'YMCKOK', 'en_US' => 'YMCKOK'],
                    'ymck' => ['de_DE' => 'YMCK', 'en_US' => 'YMCK'],
                    'monochrome' => ['de_DE' => 'Monochrom', 'en_US' => 'Monochrome'],
                    'overlay' => ['de_DE' => 'Overlay / Schutzfilm', 'en_US' => 'Overlay'],
                    'retransfer' => ['de_DE' => 'Retransfer-Film', 'en_US' => 'Retransfer film'],
                    'other' => ['de_DE' => 'Weitere', 'en_US' => 'Other'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_RIBBON_COLOR' => [
            'name' => 'Farbbandfarbe',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 570,
        ],
        'CN_RIBBON_YIELD' => [
            'name' => 'Kapazität / Reichweite (Karten)',
            'type' => IntegerAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_INTEGER,
            'position' => 580,
        ],
        'CN_COMPATIBLE_PRINTERS' => [
            'name' => 'Kompatible Druckermodelle',
            'type' => TextareaAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 590,
        ],

        // ---------------------------------------------------------------------
        // Barcode scanners
        // ---------------------------------------------------------------------
        'CN_BARCODE_DIMENSION' => [
            'name' => 'Barcode-Typ',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 610,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    '1d' => ['de_DE' => '1D', 'en_US' => '1D'],
                    '2d' => ['de_DE' => '2D', 'en_US' => '2D'],
                    'dpm' => ['de_DE' => 'DPM', 'en_US' => 'DPM'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_SCAN_ENGINE' => [
            'name' => 'Scan-Technologie',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 620,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'laser' => ['de_DE' => 'Laser', 'en_US' => 'Laser'],
                    'linear_imager' => ['de_DE' => 'Linear Imager', 'en_US' => 'Linear imager'],
                    'area_imager' => ['de_DE' => 'Area Imager', 'en_US' => 'Area imager'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_SCANNER_INTERFACES' => [
            'name' => 'Scanner-Schnittstellen',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 630,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'usb' => ['de_DE' => 'USB', 'en_US' => 'USB'],
                    'rs232' => ['de_DE' => 'RS-232', 'en_US' => 'RS-232'],
                    'bluetooth' => ['de_DE' => 'Bluetooth', 'en_US' => 'Bluetooth'],
                    'wifi' => ['de_DE' => 'WLAN / Wi-Fi', 'en_US' => 'Wi-Fi'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_WIRELESS' => [
            'name' => 'Kabellos',
            'type' => CheckboxAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_BOOLEAN,
            'position' => 640,
        ],
        'CN_WIRELESS_RANGE' => [
            'name' => 'Funkreichweite',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 650,
        ],
        'CN_DROP_RESISTANCE' => [
            'name' => 'Sturzfestigkeit',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 660,
        ],
        'CN_SUPPORTED_SYMBOLOGIES' => [
            'name' => 'Unterstützte Codes / Symbologien',
            'type' => TextareaAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 670,
        ],

        // ---------------------------------------------------------------------
        // Time attendance
        // ---------------------------------------------------------------------
        'CN_IDENTIFICATION_METHODS' => [
            'name' => 'Identifikationsmethoden',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 700,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'rfid' => ['de_DE' => 'RFID / Karte', 'en_US' => 'RFID / Card'],
                    'fingerprint' => ['de_DE' => 'Fingerabdruck', 'en_US' => 'Fingerprint'],
                    'face' => ['de_DE' => 'Gesichtserkennung', 'en_US' => 'Face recognition'],
                    'pin' => ['de_DE' => 'PIN / Tastatur', 'en_US' => 'PIN / Keypad'],
                    'barcode' => ['de_DE' => 'Barcode / QR-Code', 'en_US' => 'Barcode / QR code'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_TERMINAL_DISPLAY' => [
            'name' => 'Display',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 710,
        ],
        'CN_POWER_SUPPLY' => [
            'name' => 'Spannungsversorgung',
            'type' => TextAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_TEXT,
            'position' => 730,
        ],
        'CN_OFFLINE_MODE' => [
            'name' => 'Offline-Betrieb',
            'type' => CheckboxAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_BOOLEAN,
            'position' => 740,
        ],

        // ---------------------------------------------------------------------
        // Access control
        // ---------------------------------------------------------------------
        'CN_ACCESS_COMPONENT_TYPE' => [
            'name' => 'Komponententyp',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 800,
            'configuration' => [
                'multiple' => false,
                'choices' => [
                    'reader' => ['de_DE' => 'Leser', 'en_US' => 'Reader'],
                    'controller' => ['de_DE' => 'Controller', 'en_US' => 'Controller'],
                    'terminal' => ['de_DE' => 'Zutrittsterminal', 'en_US' => 'Access terminal'],
                    'door_module' => ['de_DE' => 'Türmodul', 'en_US' => 'Door module'],
                    'accessory' => ['de_DE' => 'Zubehör', 'en_US' => 'Accessory'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_ACCESS_PROTOCOLS' => [
            'name' => 'Zutrittsprotokolle',
            'type' => SelectAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_JSON,
            'position' => 810,
            'configuration' => [
                'multiple' => true,
                'choices' => [
                    'osdp' => ['de_DE' => 'OSDP', 'en_US' => 'OSDP'],
                    'wiegand' => ['de_DE' => 'Wiegand', 'en_US' => 'Wiegand'],
                    'rs485' => ['de_DE' => 'RS-485', 'en_US' => 'RS-485'],
                    'tcpip' => ['de_DE' => 'TCP/IP', 'en_US' => 'TCP/IP'],
                ],
                'min' => null,
                'max' => null,
            ],
        ],
        'CN_NUMBER_OF_DOORS' => [
            'name' => 'Anzahl Türen',
            'type' => IntegerAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_INTEGER,
            'position' => 820,
        ],
        'CN_POE' => [
            'name' => 'PoE',
            'type' => CheckboxAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_BOOLEAN,
            'position' => 830,
        ],
        'CN_RELAY_OUTPUTS' => [
            'name' => 'Relaisausgänge',
            'type' => IntegerAttributeType::TYPE,
            'storage' => AttributeValueInterface::STORAGE_INTEGER,
            'position' => 840,
        ],
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Creates/updates every Cardnext attribute definition.
     *
     * @return array{created: int, updated: int}
     */
    public function ensureDefinitions(): array
    {
        $repository = $this->entityManager->getRepository(ProductAttribute::class);
        $created = 0;
        $updated = 0;

        foreach (self::DEFINITIONS as $code => $definition) {
            /** @var ProductAttribute|null $attribute */
            $attribute = $repository->findOneBy(['code' => $code]);
            $isNew = $attribute === null;

            if ($isNew) {
                $attribute = new ProductAttribute();
                $attribute->setCode($code);
                $this->entityManager->persist($attribute);
                ++$created;
            } else {
                ++$updated;
            }

            $attribute->setCurrentLocale(self::LOCALE);
            $attribute->setFallbackLocale(self::LOCALE);
            $attribute->setName($definition['name']);
            $attribute->setType($definition['type']);
            $attribute->setStorageType($definition['storage']);
            $attribute->setPosition($definition['position']);
            $attribute->setTranslatable(false);
            $attribute->setConfiguration($definition['configuration'] ?? []);
        }

        $this->entityManager->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Applies all missing blank attribute values for the product's Cardnext profile.
     *
     * Blank values are intentional: they make the fields immediately available in the
     * Sylius product form. Sylius removes values that remain empty when the product form
     * is saved.
     *
     * @return array{profile: string|null, added: int, total: int}
     */
    public function applyToProduct(Product $product): array
    {
        $this->ensureDefinitions();

        $profileCode = $this->resolveProfileCode($product);
        if ($profileCode === null) {
            return ['profile' => null, 'added' => 0, 'total' => 0];
        }

        $codes = array_values(array_unique(array_merge(
            self::COMMON,
            self::PROFILES[$profileCode],
        )));

        $attributeRepository = $this->entityManager->getRepository(ProductAttribute::class);
        $added = 0;

        foreach ($codes as $code) {
            if ($product->hasAttributeByCodeAndLocale($code, self::LOCALE)) {
                continue;
            }

            /** @var ProductAttribute|null $attribute */
            $attribute = $attributeRepository->findOneBy(['code' => $code]);
            if ($attribute === null) {
                throw new \LogicException(sprintf('Cardnext product attribute "%s" is missing.', $code));
            }

            $value = new ProductAttributeValue();
            $value->setAttribute($attribute);
            $value->setLocaleCode(null);
            $product->addAttribute($value);
            $this->entityManager->persist($value);

            ++$added;
        }

        $this->entityManager->flush();

        return [
            'profile' => $profileCode,
            'added' => $added,
            'total' => count($codes),
        ];
    }

    public function resolveProfileCode(Product $product): ?string
    {
        $taxon = $product->getMainTaxon();

        while ($taxon !== null) {
            $code = $taxon->getCode();
            if ($code !== null && isset(self::PROFILES[$code])) {
                return $code;
            }

            $taxon = $taxon->getParent();
        }

        return null;
    }

    public function getProfileLabel(?string $profileCode): ?string
    {
        return match ($profileCode) {
            'CARD_PRINTERS' => 'Kartendrucker',
            'RFID_READERS' => 'RFID-Leser',
            'PLASTIC_CARDS' => 'Plastikkarten',
            'CARD_ACCESSORIES' => 'Kartenzubehör',
            'RIBBONS' => 'Farbbänder',
            'BARCODE_SCANNERS' => 'Barcode-Scanner',
            'TIME_ATTENDANCE' => 'Zeiterfassung',
            'ACCESS_CONTROL' => 'Zutrittskontrolle',
            default => null,
        };
    }

    /**
     * @return array<string, array{name: string, type: string, position: int}>
     */
    public function getDefinitionsForProfile(string $profileCode): array
    {
        if (!isset(self::PROFILES[$profileCode])) {
            return [];
        }

        $codes = array_values(array_unique(array_merge(self::COMMON, self::PROFILES[$profileCode])));
        $result = [];

        foreach ($codes as $code) {
            $definition = self::DEFINITIONS[$code];
            $result[$code] = [
                'name' => $definition['name'],
                'type' => $definition['type'],
                'position' => $definition['position'],
            ];
        }

        uasort($result, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $result;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getProfiles(): array
    {
        return self::PROFILES;
    }
}
