<?php

declare(strict_types=1);

namespace App\Command;

use App\Launch\LaunchCheckRunner;
use App\Launch\LaunchResultRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:launch-check', description: 'Runs read-only database, catalog, CMS, routing and asset launch-readiness checks.')]
final class CardnextLaunchCheckCommand extends Command
{
    public function __construct(private readonly LaunchCheckRunner $runner, private readonly LaunchResultRenderer $renderer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!is_string($format) || !in_array($format, ['text', 'json'], true)) {
            throw new \InvalidArgumentException('--format must be "text" or "json".');
        }
        $result = $this->runner->run();
        $this->renderer->render(new SymfonyStyle($input, $output), $result, $format === 'json');

        return $result->hasCriticalIssues() ? Command::FAILURE : Command::SUCCESS;
    }
}
