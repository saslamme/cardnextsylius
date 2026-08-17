<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cardnext:check-public-slugs',
    description: 'Reports product/taxon public-slug collisions without changing data.',
)]
final class CardnextCheckPublicSlugsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $collisions = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                product_translation.locale,
                product_translation.slug,
                product.code AS product_code,
                taxon.code AS taxon_code
            FROM sylius_product_translation product_translation
            INNER JOIN sylius_product product ON product.id = product_translation.translatable_id
            INNER JOIN sylius_taxon_translation taxon_translation
                ON taxon_translation.locale = product_translation.locale
                AND taxon_translation.slug = product_translation.slug
            INNER JOIN sylius_taxon taxon ON taxon.id = taxon_translation.translatable_id
            ORDER BY product_translation.locale, product_translation.slug, product.code, taxon.code
            SQL);

        if ($collisions === []) {
            $io->success('No product/taxon public-slug collisions found.');

            return Command::SUCCESS;
        }

        $io->error(sprintf('%d product/taxon public-slug collision(s) found.', count($collisions)));
        $io->table(['Locale', 'Slug', 'Product code', 'Taxon code'], $collisions);

        return Command::FAILURE;
    }
}
