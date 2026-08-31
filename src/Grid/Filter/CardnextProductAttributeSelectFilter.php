<?php

declare(strict_types=1);

namespace App\Grid\Filter;

use Sylius\Bundle\GridBundle\Doctrine\ORM\DataSource as OrmDataSource;
use Sylius\Bundle\GridBundle\Form\Type\Filter\SelectFilterType;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sylius.grid_filter', [
    'type' => 'cardnext_attribute_select',
    'form_type' => SelectFilterType::class,
])]
final class CardnextProductAttributeSelectFilter implements FilterInterface
{
    /** @param array<string, mixed> $options */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if ($data === null || $data === '' || $data === []) {
            return;
        }

        if (!$dataSource instanceof OrmDataSource) {
            throw new \InvalidArgumentException('The Cardnext attribute filter requires the Doctrine ORM grid driver.');
        }

        $attributeCode = $options['attribute_code'] ?? null;
        if (!is_string($attributeCode) || $attributeCode === '') {
            throw new \InvalidArgumentException('The Cardnext attribute filter requires the "attribute_code" option.');
        }

        $values = is_array($data) ? array_values(array_filter($data, 'is_scalar')) : [$data];
        if ($values === []) {
            return;
        }

        $queryBuilder = $dataSource->getQueryBuilder();
        $rootAlias = $queryBuilder->getRootAliases()[0] ?? 'o';
        $suffix = preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?: 'facet';
        $valueAlias = 'cnv_' . $suffix;
        $attributeAlias = 'cna_' . $suffix;
        $codeParameter = 'cn_code_' . $suffix;

        $queryBuilder
            ->innerJoin($rootAlias . '.attributes', $valueAlias)
            ->innerJoin($valueAlias . '.attribute', $attributeAlias)
            ->andWhere($attributeAlias . '.code = :' . $codeParameter)
            ->setParameter($codeParameter, $attributeCode)
            ->distinct()
        ;

        $valueExpressions = [];
        foreach ($values as $index => $value) {
            $parameter = sprintf('cn_value_%s_%d', $suffix, $index);
            $valueExpressions[] = $queryBuilder->expr()->like($valueAlias . '.json', ':' . $parameter);
            // @phpstan-ignore cast.string
            $queryBuilder->setParameter($parameter, '%"' . (string) $value . '"%');
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$valueExpressions));
    }
}
