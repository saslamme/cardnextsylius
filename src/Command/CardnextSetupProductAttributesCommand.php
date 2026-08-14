<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Product\Product;
use App\Service\ProductAttributeProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:setup-product-attributes',
    description: 'Creates the standardized Cardnext product attribute catalogue.',
)]
final class CardnextSetupProductAttributesCommand extends Command
{
    public function __construct(
        private readonly ProductAttributeProfileService $profiles,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'apply-existing',
            null,
            InputOption::VALUE_NONE,
            'Also applies the matching attribute profile to all products with a known Cardnext main taxon.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->profiles->ensureDefinitions();

            $io->success(sprintf(
                'Cardnext product attributes ready: %d created, %d updated.',
                $result['created'],
                $result['updated'],
            ));

            $rows = [];
            foreach ($this->profiles->getProfiles() as $profileCode => $codes) {
                $rows[] = [
                    $profileCode,
                    $this->profiles->getProfileLabel($profileCode) ?? $profileCode,
                    count($this->profiles->getDefinitionsForProfile($profileCode)),
                ];
            }

            $io->table(['Profil', 'Produktbereich', 'Felder inkl. Basis'], $rows);

            if ($input->getOption('apply-existing')) {
                /** @var list<Product> $products */
                $products = $this->entityManager->getRepository(Product::class)->findAll();

                $applied = 0;
                $added = 0;

                foreach ($products as $product) {
                    $applyResult = $this->profiles->applyToProduct($product);
                    if ($applyResult['profile'] !== null) {
                        ++$applied;
                        $added += $applyResult['added'];
                    }
                }

                $io->note(sprintf(
                    'Profiles applied to %d product(s); %d missing attribute values added.',
                    $applied,
                    $added,
                ));
            }

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
