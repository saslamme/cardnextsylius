<?php

declare(strict_types=1);

namespace App\Command;

use App\ProductImage\ProductImageImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:product-images:import', description: 'Safely imports product images from a semicolon-separated manifest.')]
final class CardnextProductImagesImportCommand extends Command
{
    public function __construct(private readonly ProductImageImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('manifest', InputArgument::REQUIRED, 'Manifest CSV with product_code;images columns')
            ->addOption('images', null, InputOption::VALUE_REQUIRED, 'Absolute directory containing source images')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and report without database or filesystem writes')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Replace each product\'s images after all requested files validate')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $manifestValue = $input->getArgument('manifest');
        if (!is_string($manifestValue) || $manifestValue === '') {
            $io->error('MANIFEST must be a file path.');

            return Command::INVALID;
        }
        $manifest = $manifestValue;
        $images = $input->getOption('images');
        if (!is_string($images) || $images === '' || !str_starts_with($images, \DIRECTORY_SEPARATOR)) {
            $io->error('--images must be an absolute directory path.');

            return Command::INVALID;
        }

        try {
            $result = $this->importer->import($manifest, $images, (bool) $input->getOption('dry-run'), (bool) $input->getOption('replace'));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        foreach ($result->warnings as $warning) {
            $io->warning($warning);
        }
        $labels = [
            'products_in_manifest' => 'Products in manifest', 'products_found' => 'Products found',
            'products_missing' => 'Products missing', 'images_requested' => 'Images requested',
            'images_valid' => 'Images valid', 'images_missing' => 'Images missing',
            'images_invalid' => 'Images invalid', 'images_already_assigned' => 'Images already assigned',
            'images_to_create' => 'Images to create', 'products_to_replace' => 'Products to replace',
        ];
        $io->table(['Result', 'Count'], array_map(static fn (string $key, string $label): array => [$label, $result->counts[$key]], array_keys($labels), $labels));
        $io->success($input->getOption('dry-run') ? 'Dry run completed; no changes were made.' : 'Product image import completed.');

        return Command::SUCCESS;
    }
}
