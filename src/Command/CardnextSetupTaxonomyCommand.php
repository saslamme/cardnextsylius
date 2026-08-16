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

#[AsCommand(name: 'app:cardnext:setup-taxonomy', description: 'Idempotently creates the final Cardnext product taxonomy.')]
final class CardnextSetupTaxonomyCommand extends Command
{
    private const LOCALE = 'de_DE';

    /** @var array<string, array{name:string, slug:string, parent:?string, legacy?:string}> */
    private const TAXONS = [
        'products' => ['name' => 'Produkte', 'slug' => 'produkte', 'parent' => null, 'legacy' => 'CARDNEXT_PRODUCTS'],
        'card_printers' => ['name' => 'Kartendrucker', 'slug' => 'kartendrucker', 'parent' => 'products', 'legacy' => 'CARD_PRINTERS'],
        'ribbons' => ['name' => 'Farbbänder', 'slug' => 'farbbaender', 'parent' => 'products', 'legacy' => 'RIBBONS'],
        'plastic_cards' => ['name' => 'Plastikkarten', 'slug' => 'plastikkarten', 'parent' => 'products', 'legacy' => 'PLASTIC_CARDS'],
        'plastic_cards_pvc' => ['name' => 'PVC-Karten', 'slug' => 'pvc-karten', 'parent' => 'plastic_cards'],
        'plastic_cards_magnetic' => ['name' => 'Magnetkarten', 'slug' => 'magnetkarten', 'parent' => 'plastic_cards'],
        'plastic_cards_chip' => ['name' => 'Chipkarten', 'slug' => 'chipkarten', 'parent' => 'plastic_cards'],
        'plastic_cards_rfid' => ['name' => 'RFID-Karten', 'slug' => 'rfid-karten', 'parent' => 'plastic_cards'],
        'plastic_cards_signature' => ['name' => 'Karten mit Unterschriftenfeld', 'slug' => 'karten-unterschriftenfeld', 'parent' => 'plastic_cards'],
        'plastic_cards_other' => ['name' => 'Sonstige Plastikkarten', 'slug' => 'sonstige-plastikkarten', 'parent' => 'plastic_cards'],
        'id_accessories' => ['name' => 'Ausweiszubehör', 'slug' => 'ausweiszubehoer', 'parent' => 'products', 'legacy' => 'CARD_ACCESSORIES'],
        'id_accessories_holders' => ['name' => 'Kartenhalter', 'slug' => 'kartenhalter', 'parent' => 'id_accessories'],
        'id_accessories_sleeves' => ['name' => 'Ausweishüllen', 'slug' => 'ausweishuellen', 'parent' => 'id_accessories'],
        'id_accessories_sleeves_hard' => ['name' => 'Hartplastik', 'slug' => 'hartplastik', 'parent' => 'id_accessories_sleeves'],
        'id_accessories_sleeves_soft' => ['name' => 'Weichplastik', 'slug' => 'weichplastik', 'parent' => 'id_accessories_sleeves'],
        'id_accessories_reels' => ['name' => 'Kartenjojos', 'slug' => 'kartenjojos', 'parent' => 'id_accessories'],
        'id_accessories_lanyards' => ['name' => 'Lanyards', 'slug' => 'lanyards', 'parent' => 'id_accessories'],
        'id_accessories_clips' => ['name' => 'Kartenclips', 'slug' => 'kartenclips', 'parent' => 'id_accessories'],
        'id_accessories_punches' => ['name' => 'Kartenlocher', 'slug' => 'kartenlocher', 'parent' => 'id_accessories'],
        'id_accessories_sets' => ['name' => 'Ausweissets', 'slug' => 'ausweissets', 'parent' => 'id_accessories'],
        'id_accessories_rfid_protection' => ['name' => 'RFID-Schutz', 'slug' => 'rfid-schutz', 'parent' => 'id_accessories'],
        'rfid_readers' => ['name' => 'RFID-Leser', 'slug' => 'rfid-leser', 'parent' => 'products', 'legacy' => 'RFID_READERS'],
        'rfid_readers_desktop' => ['name' => 'Desktop- & USB-Leser', 'slug' => 'desktop-usb-leser', 'parent' => 'rfid_readers'],
        'rfid_readers_mount' => ['name' => 'Montageleser', 'slug' => 'montageleser', 'parent' => 'rfid_readers'],
        'rfid_readers_oem' => ['name' => 'OEM-Leser', 'slug' => 'oem-leser', 'parent' => 'rfid_readers'],
        'rfid_readers_accessories' => ['name' => 'RFID-Leser Zubehör', 'slug' => 'zubehoer', 'parent' => 'rfid_readers'],
        'rfid_transponders' => ['name' => 'RFID-Transponder', 'slug' => 'rfid-transponder', 'parent' => 'products'],
        'rfid_transponder_cards' => ['name' => 'RFID-Karten', 'slug' => 'rfid-karten', 'parent' => 'rfid_transponders'],
        'rfid_transponder_keyfobs' => ['name' => 'RFID-Keyfobs', 'slug' => 'rfid-keyfobs', 'parent' => 'rfid_transponders'],
        'barcode_scanners' => ['name' => 'Barcode-Scanner', 'slug' => 'barcode-scanner', 'parent' => 'products', 'legacy' => 'BARCODE_SCANNERS'],
        'cleaning_material' => ['name' => 'Reinigungsmaterial', 'slug' => 'reinigungsmaterial', 'parent' => 'products'],
        'card_printer_accessories' => ['name' => 'Kartendrucker-Zubehör & Ersatzteile', 'slug' => 'kartendrucker-zubehoer-ersatzteile', 'parent' => 'products'],
        'card_software' => ['name' => 'Kartensoftware', 'slug' => 'kartensoftware', 'parent' => 'products'],
        'access_control' => ['name' => 'Zutrittskontrolle', 'slug' => 'zutrittskontrolle', 'parent' => 'products', 'legacy' => 'ACCESS_CONTROL'],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(Taxon::class);
        $taxons = [];
        $positions = [];
        $this->connection->beginTransaction();

        try {
            foreach (self::TAXONS as $code => $definition) {
                /** @var Taxon|null $taxon */
                $taxon = $repository->findOneBy(['code' => $code]);
                if (!$taxon instanceof Taxon && isset($definition['legacy'])) {
                    $taxon = $repository->findOneBy(['code' => $definition['legacy']]);
                }
                if (!$taxon instanceof Taxon) {
                    $taxon = new Taxon();
                    $this->entityManager->persist($taxon);
                }
                $taxon->setCode($code);
                $taxon->setCurrentLocale(self::LOCALE);
                $taxon->setFallbackLocale(self::LOCALE);
                $taxon->setName($definition['name']);
                $taxon->setSlug($definition['slug']);
                $parentCode = $definition['parent'];
                $taxon->setParent($parentCode !== null ? $taxons[$parentCode] : null);
                $taxon->setPosition($positions[$parentCode ?? 'root'] = ($positions[$parentCode ?? 'root'] ?? 0) + 1);
                $taxons[$code] = $taxon;
            }
            $this->entityManager->flush();
            $this->connection->executeStatement('UPDATE sylius_channel SET menu_taxon_id = :id WHERE code = :channel', ['id' => $taxons['products']->getId(), 'channel' => 'CARDNEXT_DE']);
            $this->connection->commit();
            $io->success(sprintf('Final Cardnext taxonomy ready (%d taxons).', count($taxons)));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
