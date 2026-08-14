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
    name: 'app:cardnext:setup-taxonomy',
    description: 'Creates and updates the Cardnext DE product taxonomy.',
)]
final class CardnextSetupTaxonomyCommand extends Command
{
    private const LOCALE = 'de_DE';
    private const CHANNEL_CODE = 'CARDNEXT_DE';
    private const ROOT_CODE = 'CARDNEXT_PRODUCTS';

    /**
     * @var array<string, array{name: string, slug: string, description: string}>
     */
    private const CATEGORIES = [
        'CARD_PRINTERS' => [
            'name' => 'Kartendrucker',
            'slug' => 'kartendrucker',
            'description' => 'Professionelle Kartendrucker für Mitarbeiterausweise, Mitgliedskarten, Besucherausweise und sichere Identmedien.',
        ],
        'RFID_READERS' => [
            'name' => 'RFID-Leser',
            'slug' => 'rfid-leser',
            'description' => 'RFID- und NFC-Leser für sichere Identifikation, Login, Zutritt und Systemintegration.',
        ],
        'PLASTIC_CARDS' => [
            'name' => 'Plastikkarten',
            'slug' => 'plastikkarten',
            'description' => 'PVC-Karten, Chipkarten und Identmedien für Kartendruck, RFID und professionelle Anwendungen.',
        ],
        'CARD_ACCESSORIES' => [
            'name' => 'Kartenzubehör',
            'slug' => 'kartenzubehoer',
            'description' => 'Kartenhalter, Lanyards, Clips und Zubehör für Ausweise und Identkarten.',
        ],
        'RIBBONS' => [
            'name' => 'Farbbänder',
            'slug' => 'farbbaender',
            'description' => 'Originale und kompatible Farbbänder sowie Verbrauchsmaterial für professionelle Kartendrucker.',
        ],
        'BARCODE_SCANNERS' => [
            'name' => 'Barcode-Scanner',
            'slug' => 'barcode-scanner',
            'description' => 'Barcode-Scanner und Datenerfassung für Handel, Lager, Logistik und Industrie.',
        ],
        'TIME_ATTENDANCE' => [
            'name' => 'Zeiterfassung',
            'slug' => 'zeiterfassung',
            'description' => 'Hardware und Identmedien für professionelle Zeit- und Anwesenheitserfassung.',
        ],
        'ACCESS_CONTROL' => [
            'name' => 'Zutrittskontrolle',
            'slug' => 'zutrittskontrolle',
            'description' => 'Komponenten und Identmedien für sichere und zuverlässige Zutrittslösungen.',
        ],
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
            /** @var Taxon|null $root */
            $root = $repository->findOneBy(['code' => self::ROOT_CODE]);
            if (null === $root) {
                $root = new Taxon();
                $root->setCode(self::ROOT_CODE);
                $this->entityManager->persist($root);
            }

            $this->configureTaxon(
                $root,
                'Produkte',
                'produkte',
                'Cardnext Produktsortiment für Kartendruck, RFID, Auto-ID, Zeiterfassung und Zutrittskontrolle.',
                0,
                null,
            );

            $createdOrUpdated = [];
            $position = 0;
            $printerTaxon = null;

            foreach (self::CATEGORIES as $code => $category) {
                ++$position;

                /** @var Taxon|null $taxon */
                $taxon = $repository->findOneBy(['code' => $code]);
                if (null === $taxon) {
                    $taxon = new Taxon();
                    $taxon->setCode($code);
                    $this->entityManager->persist($taxon);
                }

                $this->configureTaxon(
                    $taxon,
                    $category['name'],
                    $category['slug'],
                    $category['description'],
                    $position,
                    $root,
                );

                if ('CARD_PRINTERS' === $code) {
                    $printerTaxon = $taxon;
                }

                $createdOrUpdated[] = [$code, $category['name'], $category['slug']];
            }

            $this->entityManager->flush();

            // Use the taxonomy root as the channel menu taxon.
            $this->connection->executeStatement(
                'UPDATE sylius_channel SET menu_taxon_id = :taxonId WHERE code = :channelCode',
                [
                    'taxonId' => $root->getId(),
                    'channelCode' => self::CHANNEL_CODE,
                ],
            );

            // Keep the existing technical test product useful for category-page testing.
            if (null !== $printerTaxon) {
                $productId = $this->connection->fetchOne(
                    'SELECT id FROM sylius_product WHERE code = :code',
                    ['code' => 'TEST_PRINTER'],
                );

                if (false !== $productId) {
                    $this->connection->executeStatement(
                        'INSERT INTO sylius_product_taxon (product_id, taxon_id, position) '
                        . 'SELECT :productId, :taxonId, 0 '
                        . 'WHERE NOT EXISTS ('
                        . 'SELECT 1 FROM sylius_product_taxon WHERE product_id = :productId AND taxon_id = :taxonId'
                        . ')',
                        [
                            'productId' => (int) $productId,
                            'taxonId' => $printerTaxon->getId(),
                        ],
                    );

                    $this->connection->executeStatement(
                        'UPDATE sylius_product SET main_taxon_id = :taxonId WHERE id = :productId',
                        [
                            'taxonId' => $printerTaxon->getId(),
                            'productId' => (int) $productId,
                        ],
                    );
                }
            }

            $this->connection->commit();

            $io->success('Cardnext DE taxonomy has been created/updated.');
            $io->table(['Code', 'Name', 'Slug'], $createdOrUpdated);
            $io->note('CARDNEXT_PRODUCTS is now configured as menu taxon for CARDNEXT_DE.');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function configureTaxon(
        Taxon $taxon,
        string $name,
        string $slug,
        string $description,
        int $position,
        ?Taxon $parent,
    ): void {
        $taxon->setCurrentLocale(self::LOCALE);
        $taxon->setFallbackLocale(self::LOCALE);
        $taxon->setName($name);
        $taxon->setSlug($slug);
        $taxon->setDescription($description);
        $taxon->setPosition($position);
        $taxon->setParent($parent);
    }
}
