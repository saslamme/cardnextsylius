<?php

declare(strict_types=1);

namespace App\Command;

use App\Cms\CmsBlockRendererRegistry;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsBlock;
use App\Entity\Cms\CmsLayout;
use App\Entity\Cms\CmsMenu;
use App\Entity\Cms\CmsMenuItem;
use App\Entity\Cms\CmsPage;
use App\Entity\Cms\CmsPageTranslation;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-type BlockDefinition array{type: string, configuration: array<string, mixed>}
 * @phpstan-type PageDefinition array{kind: string, code: string, slug: string, title: string, metaTitle: string, metaDescription: string, lead: string, blocks: list<BlockDefinition>}
 * @phpstan-type ChannelDefinition array{locale: string, pages: list<PageDefinition>}
 */
#[AsCommand(
    name: 'app:cardnext:setup-brand-support',
    description: 'Creates the channel-specific Identible and Inplastor support and download pages.',
)]
final class CardnextSetupBrandSupportCommand extends Command
{
    private const LAYOUT_CODE = 'brand_service';
    private const MENU_CODE = 'footer_service';

    /** @var list<string> */
    private const BLOCK_TYPES = ['hero', 'link_cards', 'faq', 'cta', 'downloads'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly CmsBlockRendererRegistry $blocks,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $channels = [];

        foreach (array_keys($this->definitions()) as $channelCode) {
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $channelCode]);
            if (!$channel instanceof Channel) {
                $io->error(sprintf('Benötigter Verkaufskanal "%s" fehlt. Bitte zuerst die Märkte/Channels einrichten.', $channelCode));

                return Command::FAILURE;
            }
            $channels[$channelCode] = $channel;
        }

        $io->section('Gefundene Channels');
        $io->listing(array_keys($channels));

        $this->connection->beginTransaction();
        try {
            [$layout, $layoutCreated] = $this->layout();
            [$menu, $menuCreated] = $this->menu();
            $createdPages = [];
            $existingPages = [];
            $createdItems = [];
            $existingItems = [];

            foreach ($this->definitions() as $channelCode => $definition) {
                $channel = $channels[$channelCode];
                $pages = [];
                foreach ($definition['pages'] as $pageDefinition) {
                    $page = $this->entityManager->getRepository(CmsPage::class)->findOneBy(['code' => $pageDefinition['code']]);
                    if ($page instanceof CmsPage) {
                        $this->assertExistingPageScope($page, $channel, $definition['locale']);
                        $existingPages[] = sprintf('%s (%s)', $pageDefinition['code'], $channelCode);
                    } else {
                        $page = $this->createPage($pageDefinition, $channel, $definition['locale'], $layout);
                        $createdPages[] = sprintf('%s (%s)', $pageDefinition['code'], $channelCode);
                    }
                    $pages[$pageDefinition['kind']] = $page;
                }

                foreach ([
                    ['label' => 'Support', 'page' => $pages['support']],
                    ['label' => 'Downloads', 'page' => $pages['downloads']],
                    ['label' => 'Kontakt', 'url' => '/kontakt'],
                ] as $navigation) {
                    if ($this->navigationExists($menu, $channel, $definition['locale'], $navigation)) {
                        $existingItems[] = sprintf('%s (%s)', $navigation['label'], $channelCode);
                        continue;
                    }

                    $this->createNavigation($menu, $channel, $definition['locale'], $navigation);
                    $createdItems[] = sprintf('%s (%s)', $navigation['label'], $channelCode);
                }
            }

            $this->entityManager->flush();
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->section('CMS-Seiten');
        $io->table(['Status', 'Seiten'], [
            ['Neu angelegt', $createdPages === [] ? '–' : implode(', ', $createdPages)],
            ['Bereits vorhanden (unverändert)', $existingPages === [] ? '–' : implode(', ', $existingPages)],
        ]);
        $io->section('Navigation footer_service');
        $io->table(['Status', 'Einträge'], [
            ['Neu angelegt', $createdItems === [] ? '–' : implode(', ', $createdItems)],
            ['Bereits vorhanden (unverändert)', $existingItems === [] ? '–' : implode(', ', $existingItems)],
        ]);
        $io->note(sprintf('Layout %s; Menü %s.', $layoutCreated ? 'neu angelegt' : 'bereits vorhanden', $menuCreated ? 'neu angelegt' : 'bereits vorhanden'));
        $io->note('Bestehende Download-Dateien und deren Channel-Zuordnungen wurden nicht verändert.');
        $io->success('Brand-Support und Downloads sind eingerichtet. Bestehende Inhalte wurden nicht überschrieben.');

        return Command::SUCCESS;
    }

    /** @return array{CmsLayout, bool} */
    private function layout(): array
    {
        $layout = $this->entityManager->getRepository(CmsLayout::class)->findOneBy(['code' => self::LAYOUT_CODE]);
        if ($layout instanceof CmsLayout) {
            $allowed = $layout->getAllowedBlockTypes();
            if (!$layout->isEnabled() || ($allowed !== null && array_diff(self::BLOCK_TYPES, $allowed) !== [])) {
                throw new \RuntimeException(sprintf('Das vorhandene Layout "%s" ist deaktiviert oder erlaubt nicht alle benötigten Blocktypen.', self::LAYOUT_CODE));
            }

            return [$layout, false];
        }

        $layout = new CmsLayout();
        $layout->setCode(self::LAYOUT_CODE);
        $layout->setName('Marken-Service');
        $layout->setRenderer('service');
        $layout->setAllowedBlockTypes(self::BLOCK_TYPES);
        $this->entityManager->persist($layout);

        return [$layout, true];
    }

    /** @return array{CmsMenu, bool} */
    private function menu(): array
    {
        $menu = $this->entityManager->getRepository(CmsMenu::class)->findOneBy(['code' => self::MENU_CODE]);
        if ($menu instanceof CmsMenu) {
            if (!$menu->isEnabled()) {
                throw new \RuntimeException(sprintf('Das benötigte Menü "%s" ist deaktiviert.', self::MENU_CODE));
            }

            return [$menu, false];
        }

        $menu = new CmsMenu();
        $menu->setCode(self::MENU_CODE);
        $menu->setName('Footer Service');
        $this->entityManager->persist($menu);

        return [$menu, true];
    }

    /** @param PageDefinition $definition */
    private function createPage(array $definition, Channel $channel, string $locale, CmsLayout $layout): CmsPage
    {
        $page = new CmsPage();
        $page->setCode($definition['code']);
        $page->setLayout($layout);
        $page->setStatus(CmsPage::STATUS_PUBLISHED);
        $page->setIncludeInSitemap(true);
        $page->addChannel($channel);

        $translation = new CmsPageTranslation();
        $translation->setLocale($locale);
        $translation->setSlug($definition['slug']);
        $translation->setTitle($definition['title']);
        $translation->setLead($definition['lead']);
        $translation->setMetaTitle($definition['metaTitle']);
        $translation->setMetaDescription($definition['metaDescription']);
        $page->addTranslation($translation);

        foreach ($definition['blocks'] as $position => $blockDefinition) {
            $errors = $this->blocks->validate($blockDefinition['type'], $blockDefinition['configuration']);
            if ($errors !== []) {
                throw new \RuntimeException(sprintf('Ungültige Block-Konfiguration für "%s": %s', $definition['code'], implode(', ', $errors)));
            }
            $block = new CmsBlock();
            $block->setLocale($locale);
            $block->setType($blockDefinition['type']);
            $block->setPosition($position + 1);
            $block->setConfiguration($blockDefinition['configuration']);
            $page->addBlock($block);
        }

        $this->entityManager->persist($page);

        return $page;
    }

    private function assertExistingPageScope(CmsPage $page, Channel $channel, string $locale): void
    {
        if ($page->getChannels()->count() !== 1 || !$page->getChannels()->contains($channel) || $page->getTranslation($locale) === null) {
            throw new \RuntimeException(sprintf(
                'Die bestehende CMS-Seite "%s" hat eine andere Channel-/Locale-Zuordnung. Sie wurde nicht verändert; bitte den Konflikt im Backend beheben.',
                $page->getCode(),
            ));
        }
    }

    /** @param array{label: string, page?: CmsPage, url?: string} $definition */
    private function navigationExists(CmsMenu $menu, Channel $channel, string $locale, array $definition): bool
    {
        foreach ($menu->getItems() as $item) {
            if ($item->getChannel() !== $channel || $item->getLocale() !== $locale) {
                continue;
            }
            if (isset($definition['page']) && $item->getTargetType() === CmsMenuItem::PAGE && $item->getPage() === $definition['page']) {
                return true;
            }
            if (isset($definition['url']) && $item->getTargetType() === CmsMenuItem::URL && $item->getExternalUrl() === $definition['url']) {
                return true;
            }
        }

        return false;
    }

    /** @param array{label: string, page?: CmsPage, url?: string} $definition */
    private function createNavigation(CmsMenu $menu, Channel $channel, string $locale, array $definition): void
    {
        $position = 1;
        foreach ($menu->getItems() as $existing) {
            if ($existing->getChannel() === $channel && $existing->getLocale() === $locale) {
                $position = max($position, $existing->getPosition() + 1);
            }
        }

        $item = new CmsMenuItem();
        $item->setMenu($menu);
        $item->setChannel($channel);
        $item->setLocale($locale);
        $item->setLabel($definition['label']);
        $item->setPosition($position);
        if (isset($definition['page'])) {
            $item->setTargetType(CmsMenuItem::PAGE);
            $item->setPage($definition['page']);
        } else {
            $item->setTargetType(CmsMenuItem::URL);
            $item->setExternalUrl($definition['url'] ?? null);
        }
        $menu->getItems()->add($item);
        $this->entityManager->persist($item);
    }

    /** @return array<string, ChannelDefinition> */
    private function definitions(): array
    {
        $cards = [
            ['icon' => 'support', 'title' => 'Technischer Support', 'text' => 'Hilfe bei Installation, Einrichtung und technischen Problemen mit unseren Produkten.', 'linkLabel' => 'Support kontaktieren', 'linkUrl' => '/kontakt'],
            ['icon' => 'download', 'title' => 'Treiber & Downloads', 'text' => 'Treiber, Firmware, Software und weitere Downloads für Ihre Produkte.', 'linkLabel' => 'Zu den Downloads', 'linkUrl' => '/downloads'],
            ['icon' => 'manual', 'title' => 'Handbücher & Dokumentation', 'text' => 'Bedienungsanleitungen, Datenblätter und technische Dokumentationen.', 'linkLabel' => 'Dokumentation finden', 'linkUrl' => '/downloads'],
            ['icon' => 'service', 'title' => 'Reparatur & Service', 'text' => 'Unterstützung bei Reparaturen, Defekten oder einem Servicefall.', 'linkLabel' => 'Service anfragen', 'linkUrl' => '/kontakt'],
        ];
        $faq = [
            ['question' => 'Wo finde ich den passenden Treiber für meinen Kartendrucker?', 'answer' => 'Auf der Seite <a href="/downloads">Downloads</a> können Sie verfügbare Treiber nach Produkt und Dokumenttyp filtern.'],
            ['question' => 'Wo finde ich Bedienungsanleitungen und Datenblätter?', 'answer' => 'Bedienungsanleitungen, Datenblätter und weitere technische Dokumente finden Sie gesammelt unter <a href="/downloads">Downloads</a>.'],
            ['question' => 'Was benötige ich für eine Supportanfrage?', 'answer' => 'Bitte halten Sie Produktbezeichnung, Seriennummer und eine möglichst genaue Fehlerbeschreibung bereit und senden Sie diese über unser <a href="/kontakt">Kontaktformular</a>.'],
            ['question' => 'Wie kann ich eine Reparatur oder einen Servicefall melden?', 'answer' => 'Melden Sie den Servicefall bitte über unser <a href="/kontakt">Kontaktformular</a>. Unser Team stimmt anschließend die nächsten Schritte mit Ihnen ab.'],
        ];
        $downloadBlocks = [[
            'type' => 'downloads',
            'configuration' => [
                'headline' => 'Downloads & technische Unterlagen',
                'text' => 'Hier finden Sie Treiber, Handbücher, Datenblätter, Software und Firmware für Ihre Produkte.',
                'showFilters' => true,
            ],
        ]];

        return [
            'IDENTIBLE_DE' => ['locale' => 'de_DE', 'pages' => [
                $this->supportDefinition('identible_support', 'Identible Service', 'Ob technische Frage, Treiber, Handbuch oder Servicefall – hier finden Sie schnell den passenden Ansprechpartner und die richtigen Unterlagen.', 'Unser Team hilft Ihnen gerne bei technischen Fragen, Produktauswahl und Servicefällen weiter.', 'Support für Kartendrucker & ID-Systeme | Identible', 'Technischer Support für Kartendrucker, ID-Systeme und Zubehör. Finden Sie Treiber, Handbücher, Downloads und Hilfe zu Ihren Produkten.', 'Schnelle Hilfe rund um Ihre Kartendrucker, ID-Systeme und Identifikationslösungen.', $cards, $faq),
                ['kind' => 'downloads', 'code' => 'identible_downloads', 'slug' => 'downloads', 'title' => 'Downloads', 'metaTitle' => 'Treiber, Handbücher & Downloads | Identible', 'metaDescription' => 'Treiber, Handbücher, Datenblätter, Software und Firmware für Kartendrucker, RFID-Systeme und weitere Identible Produkte.', 'lead' => 'Finden Sie schnell die passenden Treiber, Handbücher und technischen Unterlagen zu Ihren Produkten.', 'blocks' => $downloadBlocks],
            ]],
            'INPLASTOR_AT' => ['locale' => 'de_AT', 'pages' => [
                $this->supportDefinition('inplastor_support', 'Inplastor Service', 'Von technischen Fragen bis zu Treibern, Dokumentationen und Servicefällen – hier finden Sie schnell die passende Unterstützung.', 'Unser Team unterstützt Sie gerne bei technischen Fragen, Produktauswahl und Servicefällen.', 'Support für Kartendrucker & Identifikationssysteme | Inplastor', 'Technischer Support für Kartendrucker, Identifikationssysteme und Zubehör in Österreich. Treiber, Handbücher, Downloads und persönliche Unterstützung.', 'Schnelle Unterstützung rund um Kartendrucker, Identifikationssysteme und Zubehör.', $cards, $faq),
                ['kind' => 'downloads', 'code' => 'inplastor_downloads', 'slug' => 'downloads', 'title' => 'Downloads', 'metaTitle' => 'Treiber, Handbücher & Downloads | Inplastor', 'metaDescription' => 'Treiber, Handbücher, Datenblätter, Software und Firmware für Kartendrucker und Identifikationssysteme von Inplastor Österreich.', 'lead' => 'Finden Sie Treiber, Handbücher und technische Unterlagen zu Ihren Produkten.', 'blocks' => $downloadBlocks],
            ]],
        ];
    }

    /**
     * @param list<array<string, string>> $cards
     * @param list<array<string, string>> $faq
     *
     * @return PageDefinition
     */
    private function supportDefinition(string $code, string $kicker, string $heroText, string $ctaText, string $metaTitle, string $metaDescription, string $lead, array $cards, array $faq): array
    {
        return ['kind' => 'support', 'code' => $code, 'slug' => 'support', 'title' => 'Support', 'metaTitle' => $metaTitle, 'metaDescription' => $metaDescription, 'lead' => $lead, 'blocks' => [
            ['type' => 'hero', 'configuration' => ['kicker' => $kicker, 'headline' => 'Wie können wir Ihnen helfen?', 'text' => $heroText]],
            ['type' => 'link_cards', 'configuration' => ['headline' => 'Service & Support', 'text' => 'Wählen Sie den passenden Bereich für Ihr Anliegen.', 'items' => $cards]],
            ['type' => 'faq', 'configuration' => ['headline' => 'Häufige Fragen', 'items' => $faq]],
            ['type' => 'cta', 'configuration' => ['headline' => 'Sie benötigen persönliche Unterstützung?', 'text' => $ctaText, 'buttonLabel' => 'Kontakt aufnehmen', 'buttonUrl' => '/kontakt']],
        ]];
    }
}
