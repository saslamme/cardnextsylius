<?php

declare(strict_types=1);

namespace App\Grid\Filter;

use Sylius\Bundle\GridBundle\Doctrine\ORM\DataSource as OrmDataSource;
use Sylius\Bundle\GridBundle\Form\Type\Filter\SelectFilterType;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sylius.grid_filter', [
    'type' => 'cardnext_attribute_boolean',
    'form_type' => SelectFilterType::class,
])]
final class CardnextProductAttributeBooleanFilter implements FilterInterface
{
    /** @param array<string, mixed> $options */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if ($data === null || $data === '') {
            return;
        }

        if (!$dataSource instanceof OrmDataSource) {
            throw new \InvalidArgumentException('The Cardnext boolean filter requires the Doctrine ORM grid driver.');
        }

        $attributeCode = $options['attribute_code'] ?? null;
        if (!is_string($attributeCode) || $attributeCode === '') {
            throw new \InvalidArgumentException('The Cardnext boolean filter requires the "attribute_code" option.');
        }

        $queryBuilder = $dataSource->getQueryBuilder();
        $rootAlias = $queryBuilder->getRootAliases()[0] ?? 'o';
        $suffix = preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?: 'facet';
        $valueAlias = 'cnbv_' . $suffix;
        $attributeAlias = 'cnba_' . $suffix;
        $codeParameter = 'cnb_code_' . $suffix;
        $valueParameter = 'cnb_value_' . $suffix;

        $queryBuilder
            ->innerJoin($rootAlias . '.attributes', $valueAlias)
            ->innerJoin($valueAlias . '.attribute', $attributeAlias)
            ->andWhere($attributeAlias . '.code = :' . $codeParameter)
            ->andWhere($valueAlias . '.boolean = :' . $valueParameter)
            ->setParameter($codeParameter, $attributeCode)
            // @phpstan-ignore cast.string
            ->setParameter($valueParameter, in_array((string) $data, ['1', 'true', 'yes'], true))
            ->distinct()
        ;
    }
}
