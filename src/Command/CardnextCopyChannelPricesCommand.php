<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Channel\Channel;
use App\Pricing\ChannelPricingCopyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:channel-prices:copy', description: 'Copies native Sylius variant prices between channels.')]
final class CardnextCopyChannelPricesCommand extends Command
{
    public function __construct(private readonly ChannelPricingCopyService $service, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('source', InputArgument::REQUIRED, 'Source channel code')
            ->addArgument('target', InputArgument::REQUIRED, 'Target channel code')
            ->addOption('adjustment', null, InputOption::VALUE_REQUIRED, 'Percentage adjustment', '0')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite existing target prices')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without database writes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(Channel::class);
        $sourceCode = $input->getArgument('source');
        $targetCode = $input->getArgument('target');
        $adjustment = $input->getOption('adjustment');
        if (!is_string($sourceCode) || !is_string($targetCode) || !is_string($adjustment)) {
            $io->error('Invalid command arguments.');

            return Command::INVALID;
        }
        $source = $repository->findOneBy(['code' => $sourceCode, 'enabled' => true]);
        $target = $repository->findOneBy(['code' => $targetCode, 'enabled' => true]);
        if (!$source instanceof Channel || !$target instanceof Channel) {
            $io->error('Both channel codes must identify enabled sales channels.');

            return Command::INVALID;
        }

        try {
            $result = $this->service->copy($source, $target, $adjustment, (bool) $input->getOption('overwrite'), (bool) $input->getOption('dry-run'));
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }
        $io->table(['Scanned', 'Eligible', 'Created', 'Overwritten', 'Skipped'], [[$result->scanned, $result->eligible, $result->created, $result->overwritten, $result->skipped()]]);
        $io->success($input->getOption('dry-run') ? 'Dry run completed; no changes were written.' : 'Channel prices copied successfully.');

        return Command::SUCCESS;
    }
}
