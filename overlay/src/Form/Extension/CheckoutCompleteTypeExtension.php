<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\IsTrue;

final class CheckoutCompleteTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('termsAccepted', CheckboxType::class, [
            'mapped' => false,
            'required' => true,
            'label' => false,
            'constraints' => [
                new IsTrue(
                    message: 'Bitte bestätigen Sie die Allgemeinen Geschäftsbedingungen.',
                ),
            ],
        ]);

        /*
         * Sylius checkout forms may use dedicated validation groups and the
         * complete form is rendered with novalidate. Therefore we enforce the
         * checkbox directly on POST_SUBMIT as an additional server-side guard.
         *
         * Adding a FormError makes the complete checkout form invalid and the
         * checkout transition cannot be completed until the checkbox is true.
         */
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();

            if (!$form->has('termsAccepted')) {
                $form->addError(new FormError(
                    'Die AGB-Bestätigung konnte nicht geprüft werden. Bitte laden Sie die Seite neu.',
                ));

                return;
            }

            if (true === $form->get('termsAccepted')->getData()) {
                return;
            }

            $form->get('termsAccepted')->addError(new FormError(
                'Bitte bestätigen Sie die Allgemeinen Geschäftsbedingungen.',
            ));
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [CompleteType::class];
    }
}
