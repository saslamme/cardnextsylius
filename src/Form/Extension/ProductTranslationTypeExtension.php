<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use Sylius\Bundle\ProductBundle\Form\Type\ProductTranslationType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ProductTranslationTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $translation = $event->getData();
            if (!$translation instanceof ProductTranslation || !($translation->getTranslatable() instanceof Product)) {
                return;
            }

            $product = $translation->getTranslatable();
            if (!$product->isConfigurable()) {
                return;
            }

            $event->getForm()->add('configuratorPath', TextType::class, [
                'label' => 'cardnext.configurator_path.label',
                'help' => 'cardnext.configurator_path.help',
                'required' => true,
                'attr' => ['placeholder' => 'plastikkarten/plastikkarten-bedrucken', 'autocomplete' => 'off'],
            ]);
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductTranslationType::class];
    }
}
