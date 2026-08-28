<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Channel\Channel;
use App\Entity\Content\LegalPage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:cardnext:setup-legal-pages',
    description: 'Creates the initial Cardnext legal pages without overwriting existing content.',
)]
final class CardnextSetupLegalPagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(LegalPage::class);
        $defaultChannel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => 'CARDNEXT_DE']);
        if (!$defaultChannel instanceof Channel) {
            $io->error('Der deutsche Hauptkanal CARDNEXT_DE fehlt. Bitte zuerst die Märkte einrichten.');

            return Command::FAILURE;
        }

        $definitions = [
            'imprint' => [
                'title' => 'Impressum',
                'metaTitle' => 'Impressum | Cardnext',
                'metaDescription' => 'Impressum und Anbieterkennzeichnung von Cardnext.',
                'file' => 'impressum.html',
            ],
            'privacy' => [
                'title' => 'Datenschutz',
                'metaTitle' => 'Datenschutz | Cardnext',
                'metaDescription' => 'Informationen zum Datenschutz und zur Verarbeitung personenbezogener Daten bei Cardnext.',
                'file' => 'datenschutz.html',
            ],
            'terms' => [
                'title' => 'Allgemeine Geschäftsbedingungen',
                'metaTitle' => 'AGB | Cardnext',
                'metaDescription' => 'Allgemeine Geschäftsbedingungen des Cardnext-Onlineshops.',
                'file' => 'agb.html',
            ],
        ];

        $created = 0;

        foreach ($definitions as $code => $definition) {
            $existing = $repository->findOneBy(['code' => $code, 'localeCode' => 'de_DE']);

            if ($existing instanceof LegalPage) {
                continue;
            }

            $file = $this->kernel->getProjectDir() . '/resources/cardnext/legal/de_DE/' . $definition['file'];
            $content = @file_get_contents($file);

            if ($content === false) {
                throw new \RuntimeException(sprintf('Seed-Datei fehlt: %s', $file));
            }

            $page = new LegalPage();
            $page->setCode($code);
            $page->setLocaleCode('de_DE');
            $page->setTitle($definition['title']);
            $page->setMetaTitle($definition['metaTitle']);
            $page->setMetaDescription($definition['metaDescription']);
            $page->setContent(trim($content));
            $page->addChannel($defaultChannel);

            $this->entityManager->persist($page);
            ++$created;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d Rechtstexte wurden neu angelegt. Bestehende Texte wurden nicht überschrieben.',
            $created,
        ));

        $io->note(
            'Bitte Betreiberangaben, eingesetzte Dienste und die B2B-Ausrichtung vor Veröffentlichung rechtlich prüfen.',
        );

        return Command::SUCCESS;
    }
}
