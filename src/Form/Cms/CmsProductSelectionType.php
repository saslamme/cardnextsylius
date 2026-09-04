<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
final class CmsProductSelectionType extends AbstractType
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn (mixed $codes): array => $this->productsForCodes(is_array($codes) ? $codes : []),
            static function (mixed $products): array {
                $codes = [];
                foreach (is_iterable($products) ? $products : [] as $product) {
                    if ($product instanceof Product && $product->getCode() !== null) {
                        $codes[$product->getCode()] = true;
                    }
                }

                return array_keys($codes);
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Product::class,
            'multiple' => true,
            'required' => false,
            'choice_label' => static fn (Product $product): string => sprintf('%s — %s', $product->getName() ?? $product->getCode(), $product->getCode()),
            'searchable_fields' => ['code', 'translations.name', 'variants.code', 'variants.manufacturerPartNumber', 'variants.gtin'],
            'max_results' => 20,
            'security' => 'ROLE_ADMINISTRATION_ACCESS',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }

    /**
     * @param array<mixed> $codes
     *
     * @return list<Product>
     */
    private function productsForCodes(array $codes): array
    {
        $orderedCodes = [];
        foreach ($codes as $code) {
            if (is_string($code) && ($code = trim($code)) !== '' && !isset($orderedCodes[$code])) {
                $orderedCodes[$code] = true;
            }
        }
        if ($orderedCodes === []) {
            return [];
        }

        /** @var list<Product> $products */
        $products = $this->entityManager->getRepository(Product::class)->createQueryBuilder('product')
            ->andWhere('product.code IN (:codes)')
            ->setParameter('codes', array_keys($orderedCodes))
            ->getQuery()->getResult();
        $byCode = [];
        foreach ($products as $product) {
            $byCode[(string) $product->getCode()] = $product;
        }

        return array_values(array_filter(array_map(static fn (string $code): ?Product => $byCode[$code] ?? null, array_keys($orderedCodes))));
    }
}
