<?php

declare(strict_types=1);

namespace App\Command;

use App\Branding\HomepageThemeResolver;
use App\Entity\Channel\Channel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:cms:homepage:brand-setup', description: 'Assigns a safe brand variant to a channel homepage without changing CMS content.')]
final class CmsHomepageBrandSetupCommand extends Command
{
    /** @var array<string, array<string, int>> */
    private const RECOMMENDED_ORDER = [
        'identible' => ['hero' => 10, 'promise_bar' => 20, 'homepage_industries' => 30, 'category_slider' => 30, 'product_slider' => 40, 'homepage_service' => 50, 'features' => 60, 'manufacturer_slider' => 70, 'homepage_promo' => 80, 'cta' => 90],
        'inplastor' => ['hero' => 10, 'promise_bar' => 20, 'category_slider' => 30, 'product_slider' => 40, 'homepage_industries' => 50, 'homepage_service' => 60, 'manufacturer_slider' => 70, 'features' => 80, 'cta' => 90],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Sales channel code')
            ->addOption('variant', null, InputOption::VALUE_REQUIRED, 'Homepage variant')
            ->addOption('normalize-order', null, InputOption::VALUE_NONE, 'Apply the documented positions to known blocks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $channelOption = $input->getOption('channel');
        $variantOption = $input->getOption('variant');
        $code = is_string($channelOption) ? trim($channelOption) : '';
        $requestedVariant = is_string($variantOption) ? strtolower(trim($variantOption)) : '';
        if ($code === '' || !in_array($requestedVariant, HomepageThemeResolver::THEMES, true)) {
            $io->error('Bitte einen Channel und eine gültige Variante (cardnext, identible oder inplastor) angeben.');

            return Command::INVALID;
        }

        $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $code]);
        if (!$channel instanceof Channel) {
            $io->error(sprintf('Der Verkaufskanal "%s" wurde nicht gefunden.', $code));

            return Command::FAILURE;
        }

        $changed = false;
        if ($channel->getThemeKey() !== $requestedVariant) {
            $channel->setThemeKey($requestedVariant);
            $changed = true;
        }

        $normalized = 0;
        if ((bool) $input->getOption('normalize-order') && isset(self::RECOMMENDED_ORDER[$requestedVariant])) {
            $occurrences = [];
            foreach ($channel->getHomepageCmsPage()?->getBlocks() ?? [] as $block) {
                if (!isset(self::RECOMMENDED_ORDER[$requestedVariant][$block->getType()])) {
                    continue;
                }
                $occurrence = $occurrences[$block->getLocale()][$block->getType()] ?? 0;
                $position = self::RECOMMENDED_ORDER[$requestedVariant][$block->getType()] + $occurrence;
                $occurrences[$block->getLocale()][$block->getType()] = $occurrence + 1;
                if ($block->getPosition() !== $position) {
                    $block->setPosition($position);
                    ++$normalized;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('Theme "%s" für %s ist aktiv; %d Blockposition(en) aktualisiert. CMS-Inhalte blieben unverändert.', $requestedVariant, $code, $normalized));

        return Command::SUCCESS;
    }
}
