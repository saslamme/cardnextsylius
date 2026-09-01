<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Product\PrinterAdvisorProfile;
use App\Form\Type\PrinterAdvisorProfileType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class PrinterAdvisorProfileTypeTest extends TypeTestCase
{
    public function testBlankIntegerInputsUseDomainDefaultsWithoutThrowing(): void
    {
        $profile = new PrinterAdvisorProfile();
        $profile->setMinAnnualVolume(250);
        $profile->setMaxAnnualVolume(500);
        $profile->setPerformanceClass(4);
        $profile->setPriority(20);

        $form = $this->factory->create(PrinterAdvisorProfileType::class, $profile);
        $form->submit([
            'minAnnualVolume' => '',
            'maxAnnualVolume' => '',
            'performanceClass' => '',
            'priority' => '',
            'singleSided' => '1',
        ]);

        self::assertSame(0, $profile->getMinAnnualVolume());
        self::assertNull($profile->getMaxAnnualVolume());
        self::assertSame(1, $profile->getPerformanceClass());
        self::assertSame(0, $profile->getPriority());
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
    }

    public function testSubmittedIntegerValuesArePreserved(): void
    {
        $profile = new PrinterAdvisorProfile();
        $form = $this->factory->create(PrinterAdvisorProfileType::class, $profile);
        $form->submit([
            'minAnnualVolume' => '100',
            'maxAnnualVolume' => '1000',
            'performanceClass' => '4',
            'priority' => '-25',
            'singleSided' => '1',
        ]);

        self::assertSame(100, $profile->getMinAnnualVolume());
        self::assertSame(1000, $profile->getMaxAnnualVolume());
        self::assertSame(4, $profile->getPerformanceClass());
        self::assertSame(-25, $profile->getPriority());
        self::assertTrue($form->isValid());
    }

    public function testInvalidIntegersAreReportedByValidation(): void
    {
        $profile = new PrinterAdvisorProfile();
        $form = $this->factory->create(PrinterAdvisorProfileType::class, $profile);
        $form->submit([
            'minAnnualVolume' => '-1',
            'maxAnnualVolume' => '0',
            'performanceClass' => '6',
            'priority' => '101',
            'singleSided' => '1',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('minAnnualVolume')->getErrors(true)->count());
        self::assertGreaterThan(0, $form->get('maxAnnualVolume')->getErrors(true)->count());
        self::assertGreaterThan(0, $form->get('performanceClass')->getErrors(true)->count());
        self::assertGreaterThan(0, $form->get('priority')->getErrors(true)->count());
    }

    public function testMaximumAnnualVolumeMustExceedMinimumWhenProvided(): void
    {
        $form = $this->factory->create(PrinterAdvisorProfileType::class, new PrinterAdvisorProfile());
        $form->submit([
            'minAnnualVolume' => '100',
            'maxAnnualVolume' => '100',
            'performanceClass' => '1',
            'priority' => '0',
            'singleSided' => '1',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('maxAnnualVolume')->getErrors(true)->count());
    }

    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [new ValidatorExtension($validator)];
    }
}
