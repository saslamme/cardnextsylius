<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\Extension\ContactTypeExtension;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\Form\Type\ContactType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Validator\Validation;

final class ContactTypeExtensionTest extends TestCase
{
    public function testAdditionalFieldsAreAvailableWhenDisplayingTheForm(): void
    {
        $form = $this->createForm();

        self::assertSame(
            ['email', 'message', 'firstName', 'lastName', 'company', 'phoneNumber', 'privacyAccepted'],
            array_keys(iterator_to_array($form)),
        );
    }

    public function testContactDetailsRemainInMailDataWhilePrivacyConsentIsUnmapped(): void
    {
        $form = $this->createForm();
        $form->submit([
            'email' => 'customer@example.com',
            'message' => 'Please call me.',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'company' => 'Analytical Engines',
            'phoneNumber' => '+49 123 456',
            'privacyAccepted' => '1',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame([
            'email' => 'customer@example.com',
            'message' => 'Please call me.',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'company' => 'Analytical Engines',
            'phoneNumber' => '+49 123 456',
        ], $form->getData());
        self::assertTrue($form->get('privacyAccepted')->getData());
    }

    private function createForm(): \Symfony\Component\Form\FormInterface
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new PreloadedExtension([], [ContactType::class => [new ContactTypeExtension()]]))
            ->getFormFactory();

        return $factory->create(ContactType::class);
    }
}
