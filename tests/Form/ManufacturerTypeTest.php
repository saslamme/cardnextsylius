<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Product\Manufacturer;
use App\Form\Type\ManufacturerType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validation;

final class ManufacturerTypeTest extends TypeTestCase
{
    public function testEditingWithoutSubmittedSlugPreservesExistingSlug(): void
    {
        $manufacturer = $this->manufacturer('ZEBRA', 'Zebra Technologies', 'zebra');

        $form = $this->factory->create(ManufacturerType::class, $manufacturer);
        $form->submit($this->formData('ZEBRA', 'Zebra Technologies'));

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSynchronized());
        self::assertSame('zebra', $manufacturer->getSlug());
    }

    public function testEditingWithEmptySlugPreservesExistingSlugWithoutTypeError(): void
    {
        $manufacturer = $this->manufacturer('ZEBRA', 'Zebra Technologies', 'zebra');
        $data = $this->formData('ZEBRA', 'Zebra Technologies');
        $data['slug'] = '';

        $this->factory->create(ManufacturerType::class, $manufacturer)->submit($data);

        self::assertSame('zebra', $manufacturer->getSlug());
    }

    public function testNewManufacturerGetsSlugFromName(): void
    {
        $manufacturer = new Manufacturer();

        $this->factory->create(ManufacturerType::class, $manufacturer)->submit(
            $this->formData('TEST_MANUFACTURER', 'Test Manufacturer'),
        );

        self::assertSame('test-manufacturer', $manufacturer->getSlug());
    }

    public function testManualSlugIsNormalized(): void
    {
        $manufacturer = new Manufacturer();
        $data = $this->formData('HID_FARGO', 'HID Fargo');
        $data['slug'] = '  HID Fargo 5000  ';

        $this->factory->create(ManufacturerType::class, $manufacturer)->submit($data);

        self::assertSame('hid-fargo-5000', $manufacturer->getSlug());
    }

    protected function getExtensions(): array
    {
        $type = new ManufacturerType(new AsciiSlugger());

        return [
            new PreloadedExtension([$type], []),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    /** @return array<string, mixed> */
    private function formData(string $code, string $name): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'website' => '',
            'description' => '',
            'position' => '0',
            'featuredPosition' => '100',
            'seoTitle' => '',
            'seoDescription' => '',
        ];
    }

    private function manufacturer(string $code, string $name, string $slug): Manufacturer
    {
        $manufacturer = new Manufacturer();
        $manufacturer->setCode($code);
        $manufacturer->setName($name);
        $manufacturer->setSlug($slug);

        return $manufacturer;
    }
}
