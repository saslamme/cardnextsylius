<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'cardnext:product-images:status', description: 'Reports product image coverage and optionally exports products without images.')]
final class CardnextProductImagesStatusCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'CSV path for products without images');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<Product> $products */
        $products = $this->entityManager->createQueryBuilder()->select('p', 'i', 'm', 'v')->from(Product::class, 'p')->leftJoin('p.images', 'i')->leftJoin('p.manufacturer', 'm')->leftJoin('p.variants', 'v')->orderBy('p.code', 'ASC')->getQuery()->getResult();
        $enabled = $withImages = 0;
        $missing = [];
        foreach ($products as $product) {
            $enabled += (int) $product->isEnabled();
            $count = $product->getImages()->count();
            $withImages += (int) ($count > 0);
            if ($count === 0) {
                $missing[] = $product;
            }
        }
        $io->table(['Status', 'Count'], [['Total products', count($products)], ['Enabled products', $enabled], ['Products with images', $withImages], ['Products without images', count($missing)]]);
        $path = $input->getOption('output');
        if (is_string($path) && $path !== '') {
            $absolute = str_starts_with($path, \DIRECTORY_SEPARATOR) ? $path : $this->projectDir . '/' . $path;
            (new Filesystem())->mkdir(dirname($absolute));
            $handle = fopen($absolute, 'wb');
            if ($handle === false) {
                throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
            }
            fputcsv($handle, ['product_code', 'manufacturer', 'manufacturer_part_number', 'name', 'enabled', 'image_count'], ';', '"', '');
            foreach ($missing as $product) {
                $variant = $this->defaultEnabledVariant($product);
                fputcsv($handle, [(string) $product->getCode(), $product->getManufacturer()?->getName() ?? '', $variant?->getManufacturerPartNumber() ?? '', (string) $product->getName(), $product->isEnabled() ? '1' : '0', '0'], ';', '"', '');
            }
            fclose($handle);
            $io->success(sprintf('Missing-image CSV written to %s.', $path));
        }

        return Command::SUCCESS;
    }

    private function defaultEnabledVariant(Product $product): ?ProductVariant
    {
        foreach ($product->getVariants() as $variant) {
            if ($variant instanceof ProductVariant && $variant->isEnabled()) {
                return $variant;
            }
        }

        return null;
    }
}
