<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Maintenance\MaintenanceContract;
use App\Entity\Order\Order;
use App\Entity\Product\Product;
use App\Enum\Support\SupportCaseType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<array<string, mixed>> */
final class SupportCaseCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'cardnext.support.form.type',
                'choices' => SupportCaseType::cases(),
                'choice_label' => static fn (SupportCaseType $type): string => 'cardnext.support.type.' . $type->value,
                'choice_value' => static fn (?SupportCaseType $type): string => $type?->value ?? '',
            ])
            ->add('order', EntityType::class, [
                'class' => Order::class,
                'choices' => $options['orders'],
                'choice_label' => static fn (Order $order): string => sprintf('%s – %s', $order->getNumber() ?? sprintf('#%d', $order->getId()), $order->getCreatedAt()?->format('d.m.Y') ?? '–'),
                'label' => 'cardnext.support.form.order',
                'placeholder' => 'cardnext.support.form.none',
                'required' => false,
            ])
            ->add('maintenanceContract', EntityType::class, [
                'class' => MaintenanceContract::class,
                'choices' => $options['maintenance_contracts'],
                'choice_label' => static function (MaintenanceContract $contract): string {
                    $label = $contract->getPrinterModel() ?? 'Gerät';
                    $label .= ' – ' . ($contract->getContractReference() ?? $contract->getErpContractId());
                    $count = count($contract->getSerialNumbers());

                    return $count > 1 ? sprintf('%s – %d Geräte', $label, $count) : $label;
                },
                'label' => 'cardnext.support.form.maintenance_contract',
                'placeholder' => 'cardnext.support.form.none',
                'required' => false,
            ])
            ->add('serialNumber', TextType::class, [
                'label' => 'cardnext.support.form.serial_number',
                'help' => 'cardnext.support.form.serial_number_help',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choices' => $options['products'],
                'choice_label' => static fn (Product $product): string => sprintf('%s – %s', $product->getName() ?? $product->getCode(), $product->getCode()),
                'label' => 'cardnext.support.form.product',
                'placeholder' => 'cardnext.support.form.none',
                'required' => false,
            ])
            ->add('subject', TextType::class, [
                'label' => 'cardnext.support.form.subject',
                'attr' => ['placeholder' => 'cardnext.support.form.subject_placeholder'],
                'constraints' => [new NotBlank(), new Length(min: 3, max: 255)],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'cardnext.support.form.description',
                'attr' => ['rows' => 8, 'placeholder' => 'cardnext.support.form.description_placeholder'],
                'constraints' => [new NotBlank(), new Length(min: 10, max: 10000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'orders' => [], 'maintenance_contracts' => [], 'products' => []]);
        $resolver->setAllowedTypes('orders', 'array');
        $resolver->setAllowedTypes('maintenance_contracts', 'array');
        $resolver->setAllowedTypes('products', 'array');
    }
}
