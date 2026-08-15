<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\Extension\CheckoutCompleteTypeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class CheckoutCompleteTypeExtensionTest extends TestCase
{
    public function testTermsMustBeAcceptedAndRemainUnmapped(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
        $builder = $factory->createBuilder(FormType::class, []);
        (new CheckoutCompleteTypeExtension())->buildForm($builder, []);

        $rejected = $builder->getForm();
        $rejected->submit([]);
        self::assertFalse($rejected->isValid());

        $acceptedBuilder = $factory->createBuilder(FormType::class, []);
        (new CheckoutCompleteTypeExtension())->buildForm($acceptedBuilder, []);
        $accepted = $acceptedBuilder->getForm();
        $accepted->submit(['termsAccepted' => '1']);

        self::assertTrue($accepted->isValid());
        self::assertSame([], $accepted->getData());
    }
}
