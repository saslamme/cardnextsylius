<?php

declare(strict_types=1);

namespace App\Form\Type\Quote;

use App\Entity\Quote\QuoteRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

final class QuoteRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('company', null, [
                'required' => true,
            ])
            ->add('contactName', null, [
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'required' => true,
            ])
            ->add('phone', null, [
                'required' => false,
            ])
            ->add('customerNumber', null, [
                'required' => false,
            ])
            ->add('street', null, [
                'required' => false,
            ])
            ->add('houseNumber', null, [
                'required' => false,
            ])
            ->add('postalCode', null, [
                'required' => false,
            ])
            ->add('city', null, [
                'required' => false,
            ])
            ->add('countryCode', CountryType::class, [
                'required' => true,
            ])
            ->add('projectReference', null, [
                'required' => false,
            ])
            ->add('requestedDeliveryDate', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('customerPurchaseOrderNumber', null, [
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 6,
                    'maxlength' => 5000,
                    'placeholder' => 'cardnext.quote.form.message_placeholder',
                ],
            ])
            ->add('needsAdvice', CheckboxType::class, [
                'required' => false,
            ])
            ->add('needsCompatibilityCheck', CheckboxType::class, [
                'required' => false,
            ])
            ->add('privacyConsent', CheckboxType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new IsTrue(message: 'cardnext.quote.validation.privacy'),
                ],
            ])
            ->add('website', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuoteRequest::class,
            'csrf_token_id' => 'cardnext_quote_submit',
        ]);
    }
}
