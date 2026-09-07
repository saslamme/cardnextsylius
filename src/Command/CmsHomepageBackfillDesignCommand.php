<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsBlock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'cardnext:cms:homepage:backfill-design', description: 'Safely adds missing design content to CMS homepages.')]
final class CmsHomepageBackfillDesignCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly TranslatorInterface $translator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('normalize-order', null, InputOption::VALUE_NONE, 'Normalize positions of known homepage blocks.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        foreach ($this->entityManager->getRepository(Channel::class)->findAll() as $channel) {
            if (!$channel instanceof Channel || null === ($page = $channel->getHomepageCmsPage())) {
                continue;
            }
            $layout = $page->getLayout();
            if ($layout?->getCode() === 'homepage' && null !== ($allowed = $layout->getAllowedBlockTypes()) && !in_array('homepage_industries', $allowed, true)) {
                $layout->setAllowedBlockTypes([...$allowed, 'homepage_industries']);
            }
            foreach ($page->getTranslations() as $translation) {
                $locale = $translation->getLocale();
                $messages = [];
                $blocks = array_values(array_filter($page->getBlocks()->toArray(), static fn (CmsBlock $block): bool => $block->getLocale() === $locale));
                foreach ($blocks as $block) {
                    $config = $block->getConfiguration();
                    if ($block->getType() === 'homepage_service' && empty($config['items'])) {
                        $config['items'] = array_map(fn (string $icon): array => ['icon' => $icon, 'title' => $this->trans("cardnext.storefront.homepage.service.items.$icon.title", $locale), 'text' => $this->trans("cardnext.storefront.homepage.service.items.$icon.text", $locale), 'linkUrl' => ''], ['advice', 'configuration', 'projects', 'consumables']);
                        $messages[] = 'service items: added';
                    } elseif ($block->getType() === 'product_slider' && empty($config['kicker'])) {
                        $config['kicker'] = $this->trans('cardnext.storefront.homepage.products.kicker', $locale); $messages[] = 'product kicker: added';
                    } elseif ($block->getType() === 'features' && empty($config['kicker'])) {
                        $config['kicker'] = $this->translator->trans('cardnext.storefront.homepage.why.kicker', ['%brand%' => $channel->getBrandName() ?: 'Cardnext'], locale: $locale); $messages[] = 'features kicker: added';
                    } elseif ($block->getType() === 'promise_bar' && isset($config['items']) && count($config['items']) === 4) {
                        $icons = ['shipping', 'advice', 'payment', 'projects']; $changed = false;
                        foreach ($config['items'] as $index => &$item) { if (($item['icon'] ?? null) === '✓') { $item['icon'] = $icons[$index]; $changed = true; } } unset($item);
                        if ($changed) { $messages[] = 'promise icons: normalized'; }
                    }
                    $block->setConfiguration($config);
                }
                if (!array_filter($blocks, static fn (CmsBlock $block): bool => $block->getType() === 'homepage_industries')) {
                    $block = new CmsBlock(); $block->setLocale($locale); $block->setType('homepage_industries'); $block->setPosition(50);
                    $definitions = [['cards', 'cardnext/homepage/industry-id-cards.webp'], ['identification', 'cardnext/homepage/industry-rfid.webp'], ['logistics', 'cardnext/homepage/industry-logistics.webp']];
                    $items = array_map(fn (array $definition): array => ['image' => $definition[1], 'alt' => $this->trans('cardnext.storefront.homepage.industries.'.$definition[0].'.image_alt', $locale), 'title' => $this->trans('cardnext.storefront.homepage.industries.'.$definition[0].'.title', $locale), 'text' => $this->trans('cardnext.storefront.homepage.industries.'.$definition[0].'.text', $locale), 'linkLabel' => $this->trans('cardnext.storefront.homepage.industries.link', $locale), 'linkUrl' => '/'], $definitions);
                    $block->setConfiguration(['kicker' => $this->trans('cardnext.storefront.homepage.industries.kicker', $locale), 'headline' => $this->trans('cardnext.storefront.homepage.industries.title', $locale), 'text' => $this->trans('cardnext.storefront.homepage.industries.text', $locale), 'items' => $items]);
                    $page->addBlock($block); $blocks[] = $block; $messages[] = 'industries: created';
                }
                if ($input->getOption('normalize-order')) { $this->normalize($blocks); $messages[] = 'order: normalized'; }
                if ($messages !== []) { $io->section(sprintf('%s / %s', $channel->getCode(), $locale)); $io->listing($messages); }
            }
        }
        $this->entityManager->flush();
        return Command::SUCCESS;
    }

    private function trans(string $key, string $locale): string { return $this->translator->trans($key, locale: $locale); }

    /** @param list<CmsBlock> $blocks */
    private function normalize(array $blocks): void
    {
        $positions = ['hero'=>10,'category_slider'=>20,'homepage_service'=>30,'product_slider'=>40,'homepage_industries'=>50,'features'=>80,'cta'=>90,'promise_bar'=>100];
        $promos = array_values(array_filter($blocks, static fn (CmsBlock $b): bool => $b->getType() === 'homepage_promo'));
        usort($promos, static fn (CmsBlock $a, CmsBlock $b): int => $a->getPosition() <=> $b->getPosition());
        foreach ($blocks as $block) { if (isset($positions[$block->getType()])) { $block->setPosition($positions[$block->getType()]); } }
        foreach ($promos as $index => $promo) { $promo->setPosition(min(79, 60 + $index * 10)); }
    }
}
