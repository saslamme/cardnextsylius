<?php

declare(strict_types=1);

namespace App\Command;

use App\CustomerImport\LegacyCustomerImporter;
use App\CustomerImport\LegacyCustomerImportOptions;
use App\Entity\Channel\Channel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:customers:import', description: 'Safely imports pipe-delimited interaktiv.shop customers.')]
final class ImportLegacyCustomersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly LegacyCustomerImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Pipe-delimited legacy customer file')
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Required Sylius sales-channel code (prompted interactively when omitted)')
            ->addOption('encoding', null, InputOption::VALUE_REQUIRED, 'Source encoding: ISO-8859-1, Windows-1252, or UTF-8', 'ISO-8859-1')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview every decision and persist nothing')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of non-empty rows')
            ->addOption('skip-existing', null, InputOption::VALUE_NONE, 'Skip existing customer identities')
            ->addOption('update-existing', null, InputOption::VALUE_NONE, 'Update safe fields on existing customers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $channels = $this->entityManager->getRepository(Channel::class)->findBy([], ['enabled' => 'DESC', 'code' => 'ASC']);
        $channelOption = $input->getOption('channel');
        $channelCode = is_string($channelOption) ? trim($channelOption) : '';
        if ($channelCode === '' && $input->isInteractive()) {
            $codes = array_map(static fn (Channel $channel): string => (string) $channel->getCode(), $channels);
            $answer = $io->askQuestion(new ChoiceQuestion('Sales channel', $codes));
            $channelCode = is_string($answer) ? $answer : '';
        }
        /** @var Channel|null $channel */
        $channel = $channelCode !== '' ? $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $channelCode]) : null;
        if (!$channel instanceof Channel) {
            $io->error('A valid --channel is required. No default channel is assumed.');

            return Command::INVALID;
        }
        $limitOption = $input->getOption('limit');
        $limit = is_string($limitOption) ? $limitOption : null;
        if ($limit !== null && (!ctype_digit((string) $limit) || (int) $limit < 1)) {
            $io->error('--limit must be a positive integer.');

            return Command::INVALID;
        }

        $file = $input->getArgument('file');
        $encoding = $input->getOption('encoding');
        if (!is_string($file) || !is_string($encoding)) {
            $io->error('File and encoding must be strings.');

            return Command::INVALID;
        }
        $dryRun = true === $input->getOption('dry-run');

        try {
            $result = $this->importer->import($file, new LegacyCustomerImportOptions($channel, $encoding, $dryRun, true === $input->getOption('update-existing'), true === $input->getOption('skip-existing'), $limit !== null ? (int) $limit : null));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->title('Cardnext legacy customer import');
        $io->definitionList(['Channel' => $channel->getCode()], ['Encoding' => $encoding]);
        $io->table(['Metric', 'Count'], [['Rows read', $result->rows], ['Valid', $result->valid], [$dryRun ? 'Would create' : 'Created', $result->created], [$dryRun ? 'Would update' : 'Updated', $result->updated], ['Skipped', $result->skipped], ['Conflicts', $result->conflicts], ['Invalid email', $result->invalidEmail], ['Invalid password hash', $result->invalidHash], ['Unknown country', $result->unknownCountry], ['Encoding errors', $result->encodingErrors], ['Other errors', $result->otherErrors]]);
        if ($result->issues !== []) {
            $io->table(['Row', 'Customer', 'Email', 'Status', 'Reason'], array_map('array_values', $result->issues));
        }
        $io->success($dryRun ? 'Dry-run completed; nothing was persisted.' : 'Import completed.');

        return Command::SUCCESS;
    }
}
