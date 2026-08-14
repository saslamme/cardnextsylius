<?php

declare(strict_types=1);

namespace App\Grid\Filter;

use Sylius\Bundle\GridBundle\Doctrine\ORM\DataSource as OrmDataSource;
use Sylius\Bundle\GridBundle\Form\Type\Filter\SelectFilterType;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sylius.grid_filter', [
    'type' => 'cardnext_manufacturer',
    'form_type' => SelectFilterType::class,
])]
final class CardnextManufacturerFilter implements FilterInterface
{
    /** @param array<string, mixed> $options */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if ($data === null || $data === '' || $data === []) {
            return;
        }

        if (!$dataSource instanceof OrmDataSource) {
            throw new \InvalidArgumentException('The Cardnext manufacturer filter requires the Doctrine ORM grid driver.');
        }

        $values = is_array($data) ? array_values(array_filter($data, 'is_scalar')) : [$data];
        if ($values === []) {
            return;
        }

        $queryBuilder = $dataSource->getQueryBuilder();
        $rootAlias = $queryBuilder->getRootAliases()[0] ?? 'o';
        $alias = 'cn_manufacturer';

        if (!in_array($alias, $queryBuilder->getAllAliases(), true)) {
            $queryBuilder->innerJoin($rootAlias . '.manufacturer', $alias);
        }

        $queryBuilder
            ->andWhere($queryBuilder->expr()->in($alias . '.code', ':cn_manufacturer_codes'))
            ->setParameter('cn_manufacturer_codes', array_map('strval', $values))
        ;
    }
}
