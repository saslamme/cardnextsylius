<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Taxonomy\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:extend-navigation-demo',
    description: 'Adds more Cardnext printer and RFID reader subcategories for mega-menu testing.',
)]
final class CardnextExtendNavigationDemoCommand extends Command
{
    private const LOCALE = 'de_DE';

    /**
     * @var array<string, list<array{code: string, name: string, slug: string, description: string}>>
     */
    private const SUBCATEGORIES = [
        'CARD_PRINTERS' => [
            [
                'code' => 'CARD_PRINTERS_RFID_ENCODER',
                'name' => 'Kartendrucker mit RFID-Encoder',
                'slug' => 'kartendrucker-mit-rfid-encoder',
                'description' => 'Kartendrucker mit integriertem RFID- oder Smartcard-Encoder für kontaktlose Identmedien.',
            ],
            [
                'code' => 'CARD_PRINTERS_MAGSTRIPE_ENCODER',
                'name' => 'Kartendrucker mit Magnetstreifen-Encoder',
                'slug' => 'kartendrucker-mit-magnetstreifen-encoder',
                'description' => 'Kartendrucker für die Personalisierung und Codierung von Magnetstreifenkarten.',
            ],
            [
                'code' => 'CARD_PRINTERS_HIGH_VOLUME',
                'name' => 'Hochleistungs-Kartendrucker',
                'slug' => 'hochleistungs-kartendrucker',
                'description' => 'Leistungsstarke Kartendrucksysteme für große Druckvolumen und zentrale Kartenausgabe.',
            ],
            [
                'code' => 'CARD_PRINTERS_MOBILE',
                'name' => 'Mobile Kartendrucker',
                'slug' => 'mobile-kartendrucker',
                'description' => 'Kompakte und flexible Kartendrucklösungen für mobile oder wechselnde Einsatzorte.',
            ],
        ],
        'RFID_READERS' => [
            [
                'code' => 'RFID_READERS_USB',
                'name' => 'USB RFID-Leser',
                'slug' => 'usb-rfid-leser',
                'description' => 'RFID- und NFC-Leser mit USB-Anschluss für PC, Notebook und industrielle Arbeitsplätze.',
            ],
            [
                'code' => 'RFID_READERS_BLUETOOTH',
                'name' => 'Bluetooth RFID-Leser',
                'slug' => 'bluetooth-rfid-leser',
                'description' => 'Kabellose RFID-Leser für mobile Anwendungen, Tablets und flexible Arbeitsplätze.',
            ],
            [
                'code' => 'RFID_READERS_MULTI_TECH',
                'name' => 'Multi-Technologie RFID-Leser',
                'slug' => 'multi-technologie-rfid-leser',
                'description' => 'Leser für mehrere RFID-Frequenzen, Kartentechnologien und unterschiedliche Identmedien.',
            ],
            [
                'code' => 'RFID_READERS_ACCESS',
                'name' => 'RFID-Leser für Zutrittskontrolle',
                'slug' => 'rfid-leser-zutrittskontrolle',
                'description' => 'RFID-Leser für Türen, Zutrittspunkte und die Integration in professionelle Zutrittssysteme.',
            ],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(Taxon::class);

        $created = 0;
        $updated = 0;
        $rows = [];

        try {
            foreach (self::SUBCATEGORIES as $parentCode => $definitions) {
                /** @var Taxon|null $parent */
                $parent = $repository->findOneBy(['code' => $parentCode]);

                if (null === $parent) {
                    throw new \RuntimeException(sprintf(
                        'Parent taxon "%s" not found. Run the Cardnext taxonomy setup first.',
                        $parentCode,
                    ));
                }

                $basePosition = count($parent->getChildren());

                foreach ($definitions as $index => $definition) {
                    /** @var Taxon|null $taxon */
                    $taxon = $repository->findOneBy(['code' => $definition['code']]);

                    if (null === $taxon) {
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
                    $taxon->setParent($parent);

                    // Existing demo categories occupy the first positions.
                    if (null === $taxon->getId()) {
                        $taxon->setPosition($basePosition + $index + 1);
                    }

                    $rows[] = [
                        $parent->getName(),
                        $definition['name'],
                        $definition['slug'],
                    ];
                }
            }

            $this->entityManager->flush();

            $io->success(sprintf(
                'Mega-menu demo extended: %d categories created, %d updated.',
                $created,
                $updated,
            ));

            $io->table(
                ['Hauptkategorie', 'Neue Unterkategorie', 'Slug'],
                $rows,
            );

            $io->note('Kartendrucker und RFID-Leser now each contain eight demo subcategories.');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
