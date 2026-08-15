<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Taxonomy\Taxon;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:setup-navigation-demo',
    description: 'Adds realistic Cardnext subcategories for navigation and mega-menu testing.',
)]
final class CardnextSetupNavigationDemoCommand extends Command
{
    private const LOCALE = 'de_DE';

    private const SUBCATEGORIES = [
        'CARD_PRINTERS' => [
            [
                'code' => 'CARD_PRINTERS_SINGLE_SIDED',
                'name' => 'Einseitige Kartendrucker',
                'slug' => 'einseitige-kartendrucker',
                'description' => 'Kartendrucker für den einseitigen Druck von Mitarbeiterausweisen, Mitgliedskarten und Identkarten.',
            ],
            [
                'code' => 'CARD_PRINTERS_DUPLEX',
                'name' => 'Beidseitige Kartendrucker',
                'slug' => 'beidseitige-kartendrucker',
                'description' => 'Kartendrucker mit automatischem Duplexdruck für professionelle Kartenanwendungen.',
            ],
            [
                'code' => 'CARD_PRINTERS_RETRANSFER',
                'name' => 'Retransfer-Kartendrucker',
                'slug' => 'retransfer-kartendrucker',
                'description' => 'Retransfer-Systeme für besonders hochwertige, randlose und sichere Kartendrucke.',
            ],
            [
                'code' => 'CARD_PRINTERS_LAMINATION',
                'name' => 'Kartendrucker mit Laminierung',
                'slug' => 'kartendrucker-mit-laminierung',
                'description' => 'Kartendrucksysteme mit Laminierung für langlebige und besonders geschützte Ausweise.',
            ],
        ],
        'RFID_READERS' => [
            [
                'code' => 'RFID_READERS_DESKTOP',
                'name' => 'Desktop-RFID-Leser',
                'slug' => 'desktop-rfid-leser',
                'description' => 'RFID- und NFC-Leser für den Einsatz am Arbeitsplatz, POS oder Terminal.',
            ],
            [
                'code' => 'RFID_READERS_EMBEDDED',
                'name' => 'Einbau-RFID-Leser',
                'slug' => 'einbau-rfid-leser',
                'description' => 'Kompakte RFID-OEM- und Einbauleser zur Integration in Geräte, Terminals und Maschinen.',
            ],
            [
                'code' => 'RFID_READERS_NFC',
                'name' => 'NFC-Leser',
                'slug' => 'nfc-leser',
                'description' => 'NFC-Leser für kontaktlose Karten, Smartphones und moderne Identifikationsanwendungen.',
            ],
            [
                'code' => 'RFID_READERS_LOGIN',
                'name' => 'RFID-Leser für Login & SSO',
                'slug' => 'rfid-leser-login-sso',
                'description' => 'RFID-Leser für sichere Benutzeranmeldung, Single Sign-on und Credential-Management.',
            ],
        ],
        'PLASTIC_CARDS' => [
            [
                'code' => 'PLASTIC_CARDS_BLANK',
                'name' => 'Blanko Plastikkarten',
                'slug' => 'blanko-plastikkarten',
                'description' => 'Weiße und farbige Blankokarten für Kartendrucker und Personalisierung.',
            ],
            [
                'code' => 'PLASTIC_CARDS_MAGSTRIPE',
                'name' => 'Magnetstreifenkarten',
                'slug' => 'magnetstreifenkarten',
                'description' => 'Plastikkarten mit Magnetstreifen für Identifikation, Zutritt und Kundenkarten.',
            ],
            [
                'code' => 'PLASTIC_CARDS_RFID',
                'name' => 'RFID- & Chipkarten',
                'slug' => 'rfid-chipkarten',
                'description' => 'Kontaktlose RFID-, NFC- und Chipkarten für professionelle Identifikationslösungen.',
            ],
            [
                'code' => 'PLASTIC_CARDS_PRINTED',
                'name' => 'Bedruckte Plastikkarten',
                'slug' => 'bedruckte-plastikkarten',
                'description' => 'Individuell bedruckte und personalisierte Plastikkarten für Unternehmen und Organisationen.',
            ],
        ],
        'CARD_ACCESSORIES' => [
            [
                'code' => 'CARD_ACCESSORIES_HOLDERS',
                'name' => 'Kartenhalter',
                'slug' => 'kartenhalter',
                'description' => 'Karten- und Ausweishalter für sicheren Schutz und komfortables Tragen.',
            ],
            [
                'code' => 'CARD_ACCESSORIES_LANYARDS',
                'name' => 'Lanyards',
                'slug' => 'lanyards',
                'description' => 'Lanyards und Schlüsselbänder für Ausweise, Besucherkarten und Events.',
            ],
            [
                'code' => 'CARD_ACCESSORIES_REELS',
                'name' => 'Ausweisjojos',
                'slug' => 'ausweisjojos',
                'description' => 'Ausweisjojos und Kartenjojos für häufig verwendete Identkarten.',
            ],
            [
                'code' => 'CARD_ACCESSORIES_CLIPS',
                'name' => 'Clips & Befestigungen',
                'slug' => 'clips-befestigungen',
                'description' => 'Clips, Klemmen und Befestigungslösungen für Kartenhalter und Ausweise.',
            ],
        ],
        'RIBBONS' => [
            [
                'code' => 'RIBBONS_EVOLIS',
                'name' => 'Evolis Farbbänder',
                'slug' => 'evolis-farbbaender',
                'description' => 'Farbbänder und Verbrauchsmaterial für Evolis Kartendrucker.',
            ],
            [
                'code' => 'RIBBONS_HID_FARGO',
                'name' => 'HID Fargo Farbbänder',
                'slug' => 'hid-fargo-farbbaender',
                'description' => 'Originale Farbbänder und Verbrauchsmaterialien für HID Fargo Kartendrucker.',
            ],
            [
                'code' => 'RIBBONS_MATICA',
                'name' => 'Matica Farbbänder',
                'slug' => 'matica-farbbaender',
                'description' => 'Farbbänder und Verbrauchsmaterial für Matica Kartendrucksysteme.',
            ],
            [
                'code' => 'RIBBONS_ZEBRA',
                'name' => 'Zebra Farbbänder',
                'slug' => 'zebra-farbbaender',
                'description' => 'Farbbänder und Verbrauchsmaterialien für Zebra Kartendrucker.',
            ],
        ],
        'BARCODE_SCANNERS' => [
            [
                'code' => 'BARCODE_SCANNERS_HANDHELD',
                'name' => 'Handscanner',
                'slug' => 'barcode-handscanner',
                'description' => 'Ergonomische Barcode-Handscanner für Handel, Lager, Logistik und Industrie.',
            ],
            [
                'code' => 'BARCODE_SCANNERS_PRESENTATION',
                'name' => 'Präsentationsscanner',
                'slug' => 'praesentationsscanner',
                'description' => 'Stationäre Präsentationsscanner für Kassenplätze, Counter und Self-Service.',
            ],
            [
                'code' => 'BARCODE_SCANNERS_WIRELESS',
                'name' => 'Kabellose Barcode-Scanner',
                'slug' => 'kabellose-barcode-scanner',
                'description' => 'Bluetooth- und Funk-Barcodescanner für flexible mobile Datenerfassung.',
            ],
            [
                'code' => 'BARCODE_SCANNERS_2D',
                'name' => '2D Barcode-Scanner',
                'slug' => '2d-barcode-scanner',
                'description' => 'Scanner für QR-Codes, DataMatrix und weitere 1D- und 2D-Barcodes.',
            ],
        ],
        'TIME_ATTENDANCE' => [
            [
                'code' => 'TIME_ATTENDANCE_TERMINALS',
                'name' => 'Zeiterfassungsterminals',
                'slug' => 'zeiterfassungsterminals',
                'description' => 'Stationäre Terminals für Arbeitszeit-, Anwesenheits- und Betriebsdatenerfassung.',
            ],
            [
                'code' => 'TIME_ATTENDANCE_RFID',
                'name' => 'RFID-Zeiterfassung',
                'slug' => 'rfid-zeiterfassung',
                'description' => 'Zeiterfassung mit RFID-Karten, Transpondern und kontaktlosen Identmedien.',
            ],
            [
                'code' => 'TIME_ATTENDANCE_MEDIA',
                'name' => 'Ausweise & Transponder',
                'slug' => 'zeiterfassung-ausweise-transponder',
                'description' => 'Mitarbeiterausweise, Schlüsselanhänger und Transponder für Zeiterfassungssysteme.',
            ],
            [
                'code' => 'TIME_ATTENDANCE_ACCESSORIES',
                'name' => 'Zeiterfassungszubehör',
                'slug' => 'zeiterfassungszubehoer',
                'description' => 'Zubehör und Ergänzungen für professionelle Zeiterfassungssysteme.',
            ],
        ],
        'ACCESS_CONTROL' => [
            [
                'code' => 'ACCESS_CONTROL_READERS',
                'name' => 'Zutrittsleser',
                'slug' => 'zutrittsleser',
                'description' => 'RFID- und Kartenleser für Türen, Tore und professionelle Zutrittssysteme.',
            ],
            [
                'code' => 'ACCESS_CONTROL_CONTROLLERS',
                'name' => 'Zutrittscontroller',
                'slug' => 'zutrittscontroller',
                'description' => 'Controller und Steuerungen für vernetzte Zutrittskontrollsysteme.',
            ],
            [
                'code' => 'ACCESS_CONTROL_TRANSPONDERS',
                'name' => 'RFID-Transponder',
                'slug' => 'zutritt-rfid-transponder',
                'description' => 'RFID-Karten, Schlüsselanhänger und weitere Identmedien für Zutrittskontrolle.',
            ],
            [
                'code' => 'ACCESS_CONTROL_ACCESSORIES',
                'name' => 'Zutrittszubehör',
                'slug' => 'zutrittszubehoer',
                'description' => 'Montage-, Anschluss- und Systemzubehör für Zutrittskontrolllösungen.',
            ],
        ],
    ];

    private const DEMO_PRODUCT_ASSIGNMENTS = [
        'CARD_PRINTERS_SINGLE_SIDED' => ['DEMO_PRINTER_001', 'DEMO_PRINTER_004', 'DEMO_COMPAT_PRINTER'],
        'CARD_PRINTERS_DUPLEX' => ['DEMO_PRINTER_002', 'DEMO_PRINTER_005', 'DEMO_PRINTER_006', 'TEST_PRINTER'],
        'CARD_PRINTERS_RETRANSFER' => ['DEMO_PRINTER_003'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(Taxon::class);
        $this->connection->beginTransaction();

        try {
            $rows = [];
            $created = 0;
            $updated = 0;

            foreach (self::SUBCATEGORIES as $parentCode => $definitions) {
                $parent = $repository->findOneBy(['code' => $parentCode]);
                if (!$parent instanceof Taxon) {
                    throw new \RuntimeException(sprintf(
                        'Parent taxon "%s" not found. Run "php bin/console app:cardnext:setup-taxonomy --env=prod" first.',
                        $parentCode,
                    ));
                }

                foreach ($definitions as $position => $definition) {
                    $taxon = $repository->findOneBy(['code' => $definition['code']]);
                    if (!$taxon instanceof Taxon) {
                        $taxon = new Taxon();
                        $taxon->setCode($definition['code']);
                        $this->entityManager->persist($taxon);
                        ++$created;
                    } else {
                        ++$updated;
                    }

                    $taxon->setCurrentLocale(self::LOCALE);
                    $taxon->setFallbackLocale(self::LOCALE);
                    $taxon->setName($definition['name']);
                    $taxon->setSlug($definition['slug']);
                    $taxon->setDescription($definition['description']);
                    $taxon->setPosition($position + 1);
                    $taxon->setParent($parent);

                    $rows[] = [$parent->getName(), $definition['code'], $definition['name'], $definition['slug']];
                }
            }

            $this->entityManager->flush();
            $assigned = $this->assignDemoProducts($repository);
            $this->connection->commit();

            $io->success(sprintf(
                'Navigation demo taxonomy ready: %d created, %d updated, %d demo assignments added.',
                $created,
                $updated,
                $assigned,
            ));
            $io->table(['Hauptkategorie', 'Code', 'Unterkategorie', 'Slug'], $rows);
            $io->note('Main taxons of products remain unchanged.');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function assignDemoProducts(object $repository): int
    {
        $assigned = 0;

        foreach (self::DEMO_PRODUCT_ASSIGNMENTS as $taxonCode => $productCodes) {
            $taxon = $repository->findOneBy(['code' => $taxonCode]);
            if (!$taxon instanceof Taxon || null === $taxon->getId()) {
                continue;
            }

            foreach ($productCodes as $productCode) {
                $productId = $this->connection->fetchOne(
                    'SELECT id FROM sylius_product WHERE code = :code',
                    ['code' => $productCode],
                );
                if (false === $productId) {
                    continue;
                }

                $assigned += $this->connection->executeStatement(
                    'INSERT INTO sylius_product_taxon (product_id, taxon_id, position) '
                    . 'SELECT :productId, :taxonId, 0 WHERE NOT EXISTS ('
                    . 'SELECT 1 FROM sylius_product_taxon WHERE product_id = :productId AND taxon_id = :taxonId'
                    . ')',
                    ['productId' => (int) $productId, 'taxonId' => (int) $taxon->getId()],
                );
            }
        }

        return $assigned;
    }
}
