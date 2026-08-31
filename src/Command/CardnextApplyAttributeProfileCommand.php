<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Product\Product;
use App\Service\ProductAttributeProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:apply-attribute-profile',
    description: 'Applies the matching Cardnext attribute profile to a product.',
)]
final class CardnextApplyAttributeProfileCommand extends Command
{
    public function __construct(
        private readonly ProductAttributeProfileService $profiles,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('productCode', InputArgument::REQUIRED, 'Sylius product code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // @phpstan-ignore cast.string
        $productCode = (string) $input->getArgument('productCode');

        /** @var Product|null $product */
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $productCode]);
        if ($product === null) {
            $io->error(sprintf('Product "%s" was not found.', $productCode));

            return Command::FAILURE;
        }

        $result = $this->profiles->applyToProduct($product);
        if ($result['profile'] === null) {
            $io->warning('No Cardnext attribute profile could be determined from the product main taxon.');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%s profile applied to "%s": %d of %d fields added.',
            $this->profiles->getProfileLabel($result['profile']) ?? $result['profile'],
            $productCode,
            $result['added'],
            $result['total'],
        ));

        return Command::SUCCESS;
    }
}
