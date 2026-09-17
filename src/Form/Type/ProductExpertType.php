<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Channel\Channel;
use App\Entity\Sales\ProductExpert;
use App\Entity\User\AdminUser;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductExpertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adminUser', EntityType::class, [
                'class' => AdminUser::class,
                'choice_label' => static fn (AdminUser $user): string => $user->getEmail() ?? $user->getUsername() ?? 'Admin User',
                'autocomplete' => true,
                'label' => 'Admin User',
                'placeholder' => 'Admin User auswählen',
            ])
            ->add('channel', EntityType::class, [
                'class' => Channel::class,
                'choice_label' => 'name',
                'label' => 'Verkaufskanal / Channel',
                'placeholder' => 'Verkaufskanal auswählen',
            ])
            ->add('displayName', null, [
                'label' => 'Anzeigename',
                'help' => 'Name, der im Frontend angezeigt wird',
            ])
            ->add('slug', null, [
                'label' => 'Slug',
                'help' => 'Öffentliche URL des Produktexperten, z. B. /experten/max-mustermann',
            ])
            ->add('introText', TextareaType::class, [
                'required' => false,
                'label' => 'Introtext',
                'help' => 'Optionaler Einleitungstext für die öffentliche Expertenseite',
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Aktiv',
                'help' => 'Nur aktive Expertenprofile sind im Frontend sichtbar.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProductExpert::class]);
    }
}
