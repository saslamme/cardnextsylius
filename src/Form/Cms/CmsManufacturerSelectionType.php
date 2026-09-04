<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Entity\Product\Manufacturer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
final class CmsManufacturerSelectionType extends AbstractType
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn (mixed $codes): array => $this->forCodes(is_array($codes) ? $codes : []),
            static function (mixed $values): array {
                $codes = [];
                foreach (is_iterable($values) ? $values : [] as $manufacturer) {
                    if ($manufacturer instanceof Manufacturer) {
                        $codes[$manufacturer->getCode()] = true;
                    }
                }

                return array_keys($codes);
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['class' => Manufacturer::class, 'multiple' => true, 'required' => false, 'choice_label' => static fn (Manufacturer $m): string => sprintf('%s — %s', $m->getName(), $m->getCode()), 'searchable_fields' => ['name', 'code', 'slug'], 'max_results' => 20, 'security' => 'ROLE_ADMINISTRATION_ACCESS']);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<Manufacturer>
     */
    private function forCodes(array $values): array
    {
        $codes = [];
        foreach ($values as $value) {
            if (is_string($value) && ($value = trim($value)) !== '') {
                $codes[$value] = true;
            }
        }
        if ($codes === []) {
            return [];
        }
        /** @var list<Manufacturer> $found */
        $found = $this->entityManager->getRepository(Manufacturer::class)->createQueryBuilder('manufacturer')->andWhere('manufacturer.code IN (:codes)')->setParameter('codes', array_keys($codes))->getQuery()->getResult();
        $byCode = [];
        foreach ($found as $manufacturer) {
            $byCode[$manufacturer->getCode()] = $manufacturer;
        }

        return array_values(array_filter(array_map(static fn (string $code): ?Manufacturer => $byCode[$code] ?? null, array_keys($codes))));
    }
}
