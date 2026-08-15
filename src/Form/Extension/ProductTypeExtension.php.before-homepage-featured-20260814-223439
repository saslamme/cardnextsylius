<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Manufacturer;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('manufacturer', EntityType::class, [
            'class' => Manufacturer::class,
            'choice_label' => 'name',
            'placeholder' => '— Kein Hersteller —',
            'required' => false,
            'label' => 'Hersteller',
            'query_builder' => static fn (EntityRepository $repository) =>
                $repository->createQueryBuilder('manufacturer')
                    ->orderBy('manufacturer.position', 'ASC')
                    ->addOrderBy('manufacturer.name', 'ASC'),
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
