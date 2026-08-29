<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\QuoteRequest;
use App\Form\Type\Quote\QuoteRequestType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class QuoteRequestTypeTest extends TypeTestCase
{
    private const REQUIRED_FIELDS = [
        'company',
        'contactName',
        'email',
        'countryCode',
        'privacyConsent',
    ];

    private const OPTIONAL_FIELDS = [
        'phone',
        'customerNumber',
        'street',
        'houseNumber',
        'postalCode',
        'city',
        'projectReference',
        'requestedDeliveryDate',
        'customerPurchaseOrderNumber',
        'message',
        'needsAdvice',
        'needsCompatibilityCheck',
        'website',
    ];

    public function testRequiredAndOptionalFieldsMatchTheEntityContract(): void
    {
        $form = $this->factory->create(QuoteRequestType::class);

        foreach (self::REQUIRED_FIELDS as $field) {
            self::assertTrue($form->get($field)->getConfig()->getRequired(), sprintf('%s should be required.', $field));
        }
        foreach (self::OPTIONAL_FIELDS as $field) {
            self::assertFalse($form->get($field)->getConfig()->getRequired(), sprintf('%s should be optional.', $field));
        }
    }

    public function testMinimalRequestIsValidAndCreatesQuoteRequest(): void
    {
        $form = $this->factory->create(QuoteRequestType::class);
        $form->submit([
            'company' => 'Cardnext GmbH',
            'contactName' => 'Erika Mustermann',
            'email' => 'erika@example.com',
            'countryCode' => 'DE',
            'privacyConsent' => '1',
        ]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertInstanceOf(QuoteRequest::class, $form->getData());
    }

    public function testExplicitlyEmptyHoneypotIsNormalizedAndDoesNotInvalidateForm(): void
    {
        $form = $this->factory->create(QuoteRequestType::class);
        $form->submit($this->validSubmission(['website' => '']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertNull($form->get('website')->getData());
    }

    public function testMissingHoneypotIsEmptyAndDoesNotInvalidateForm(): void
    {
        $form = $this->factory->create(QuoteRequestType::class);
        $form->submit($this->validSubmission());

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertNull($form->get('website')->getData());
    }

    public function testFilledHoneypotRetainsItsBlockingValue(): void
    {
        $form = $this->factory->create(QuoteRequestType::class);
        $form->submit($this->validSubmission(['website' => 'spam']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('spam', $form->get('website')->getData());
    }

    public function testOptionalFieldsDoNotRequestHtmlRequiredValidation(): void
    {
        $view = $this->factory->create(QuoteRequestType::class)->createView();

        foreach (self::OPTIONAL_FIELDS as $field) {
            self::assertFalse($view->children[$field]->vars['required'], sprintf('%s would render an HTML required attribute.', $field));
        }
    }

    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([new QuoteRequestType()], []),
            new ValidatorExtension(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()),
        ];
    }

    /** @param array<string, string> $overrides */
    private function validSubmission(array $overrides = []): array
    {
        return array_merge([
            'company' => 'Cardnext GmbH',
            'contactName' => 'Erika Mustermann',
            'email' => 'erika@example.com',
            'countryCode' => 'DE',
            'privacyConsent' => '1',
        ], $overrides);
    }
}
