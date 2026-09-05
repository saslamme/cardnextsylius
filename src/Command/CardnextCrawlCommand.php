<?php

declare(strict_types=1);

namespace App\Command;

use App\Launch\LaunchResultRenderer;
use App\Launch\PublicStorefrontCrawler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:crawl', description: 'Crawls same-origin storefront links and assets using read-only GET requests.')]
final class CardnextCrawlCommand extends Command
{
    public function __construct(private readonly PublicStorefrontCrawler $crawler, private readonly LaunchResultRenderer $renderer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Storefront URL, including its channel hostname')
            ->addOption('max-pages', null, InputOption::VALUE_REQUIRED, 'Maximum number of same-origin URLs to request', '250')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $baseUrl = $input->getOption('base-url');
        $maxPages = filter_var($input->getOption('max-pages'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($maxPages === false || !is_string($format) || !in_array($format, ['text', 'json'], true) || !is_string($baseUrl)) {
            throw new \InvalidArgumentException('--base-url is required, --max-pages must be positive and --format must be "text" or "json".');
        }
        $result = $this->crawler->crawl($baseUrl, $maxPages);
        $this->renderer->render(new SymfonyStyle($input, $output), $result, $format === 'json');

        return $result->hasCriticalIssues() ? Command::FAILURE : Command::SUCCESS;
    }
}
