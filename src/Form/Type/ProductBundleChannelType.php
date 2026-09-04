<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Channel\Channel;
use App\Entity\Product\ProductBundleChannel;
use App\Form\DataTransformer\BasisPointsToPercentageTransformer;
use App\Form\DataTransformer\MinorUnitsToMoneyTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductBundleChannelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channel', EntityType::class, [
                'class' => Channel::class,
                'choice_label' => static fn (Channel $channel): string => sprintf('%s (%s)', $channel->getName() ?: $channel->getCode(), $channel->getCode()),
                'label' => 'Verkaufskanal',
                'placeholder' => 'Channel auswählen',
            ])
            ->add('enabled', CheckboxType::class, ['required' => false, 'label' => 'Aktiv'])
            ->add('discountType', ChoiceType::class, [
                'choices' => ['Kein Rabatt' => ProductBundleChannel::DISCOUNT_NONE, 'Fester Betrag' => ProductBundleChannel::DISCOUNT_FIXED, 'Prozent' => ProductBundleChannel::DISCOUNT_PERCENT],
                'label' => 'Rabattart',
                'attr' => ['data-action' => 'change->cardnext-bundle-collection#discountChanged'],
            ])
            ->add('fixedDiscount', TextType::class, ['required' => false, 'label' => 'Rabattbetrag', 'help' => 'Betrag in Euro, z. B. 25,00', 'attr' => ['inputmode' => 'decimal']])
            ->add('percentageDiscount', TextType::class, ['required' => false, 'label' => 'Rabatt in %', 'help' => 'Prozentwert, z. B. 5 oder 7,5', 'attr' => ['inputmode' => 'decimal']]);

        $builder->get('fixedDiscount')->addModelTransformer(new MinorUnitsToMoneyTransformer());
        $builder->get('percentageDiscount')->addModelTransformer(new BasisPointsToPercentageTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProductBundleChannel::class]);
    }
}
