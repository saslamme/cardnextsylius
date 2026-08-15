<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\CoreBundle\Form\Type\ContactType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ContactTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Vorname',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Bitte geben Sie Ihren Vornamen ein.',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Name',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Bitte geben Sie Ihren Namen ein.',
                    ]),
                ],
            ])
            ->add('company', TextType::class, [
                'label' => 'Unternehmen',
                'required' => false,
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Telefonnummer',
                'required' => false,
            ])
            ->add('privacyAccepted', CheckboxType::class, [
                'label' => 'Ich habe die Datenschutzbestimmungen gelesen und akzeptiere diese.',
                'required' => true,
                'constraints' => [
                    new IsTrue([
                        'message' => 'Bitte akzeptieren Sie die Datenschutzbestimmungen.',
                    ]),
                ],
            ])
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [ContactType::class];
    }
}
