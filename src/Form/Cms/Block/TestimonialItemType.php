<?php

declare(strict_types=1);

namespace App\Form\Cms\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class TestimonialItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quote', TextareaType::class, ['label' => 'Zitat', 'attr' => ['rows' => 5], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 1000)]])
            ->add('name', TextType::class, ['label' => 'Name', 'required' => false, 'constraints' => [new Assert\Length(max: 150)]])
            ->add('role', TextType::class, ['label' => 'Rolle / Position', 'required' => false, 'constraints' => [new Assert\Length(max: 150)]])
            ->add('company', TextType::class, ['label' => 'Unternehmen', 'required' => false, 'constraints' => [new Assert\Length(max: 200)]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                if (!is_array($data)) {
                    return;
                }

                $name = $data['name'] ?? null;
                $company = $data['company'] ?? null;
                if ((!is_string($name) || trim($name) === '') && (!is_string($company) || trim($company) === '')) {
                    $context->buildViolation('Bitte Name oder Unternehmen angeben.')->addViolation();
                }
            })],
        ]);
    }
}
