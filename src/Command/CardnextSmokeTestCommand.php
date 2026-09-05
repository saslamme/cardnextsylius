<?php

declare(strict_types=1);

namespace App\Command;

use App\Launch\LaunchResultRenderer;
use App\Launch\StorefrontSmokeTester;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:smoke-test', description: 'Runs deterministic, read-only HTTP storefront smoke checks without a browser.')]
final class CardnextSmokeTestCommand extends Command
{
    public function __construct(private readonly StorefrontSmokeTester $tester, private readonly LaunchResultRenderer $renderer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Storefront URL, including its channel hostname')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Path to test; repeat to override the defaults')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $baseUrl = $input->getOption('base-url');
        if (!is_string($format) || !in_array($format, ['text', 'json'], true) || !is_string($baseUrl)) {
            throw new \InvalidArgumentException('--base-url is required and --format must be "text" or "json".');
        }
        /** @var list<string> $paths */
        $paths = $input->getOption('path');
        $paths = $paths === [] ? ['/', '/impressum', '/datenschutz', '/agb', '/robots.txt', '/favicon.ico'] : $paths;
        $result = $this->tester->test($baseUrl, $paths);
        $this->renderer->render(new SymfonyStyle($input, $output), $result, $format === 'json');

        return $result->hasCriticalIssues() ? Command::FAILURE : Command::SUCCESS;
    }
}
