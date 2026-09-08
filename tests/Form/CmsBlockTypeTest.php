<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Cms\CmsBlock;
use App\Form\Cms\Block\GalleryItemType;
use App\Form\Cms\Block\HomepageIndustryItemType;
use App\Form\Cms\CmsBlockType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class CmsBlockTypeTest extends TestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new GalleryItemType())
            ->addType(new HomepageIndustryItemType())
            ->addType(new CmsBlockType())
            ->getFormFactory()
        ;
    }

    /** @return iterable<string, array{string}> */
    public static function topLevelImageBlockTypes(): iterable
    {
        yield 'hero' => ['hero'];
        yield 'homepage service' => ['homepage_service'];
        yield 'image and text' => ['image_text'];
        yield 'call to action' => ['cta'];
        yield 'homepage promotion' => ['homepage_promo'];
    }

    #[DataProvider('topLevelImageBlockTypes')]
    public function testStoredImagePathIsNotPassedToFileField(string $type): void
    {
        $configuration = ['image' => 'cardnext/homepage/hero-card-printer.webp'];
        $block = $this->block($type, $configuration);

        $form = $this->formFactory->create(CmsBlockType::class, $block, [
            'locale_choices' => ['Deutsch' => 'de_DE'],
        ]);

        self::assertNull($form->get('image')->getData());
        self::assertNull($form->get('image')->getViewData());
        self::assertNull($form->createView()->children['image']->vars['data']);
        self::assertSame($configuration, $block->getConfiguration());
    }

    /** @return iterable<string, array{string}> */
    public static function nestedImageBlockTypes(): iterable
    {
        yield 'gallery' => ['gallery'];
        yield 'homepage industries' => ['homepage_industries'];
    }

    #[DataProvider('nestedImageBlockTypes')]
    public function testStoredNestedImagePathIsMovedToExistingImage(string $type): void
    {
        $configuration = [
            'items' => [[
                'image' => 'cardnext/homepage/industry.webp',
                'alt' => 'Example image',
            ]],
        ];
        $block = $this->block($type, $configuration);

        $form = $this->formFactory->create(CmsBlockType::class, $block, [
            'locale_choices' => ['Deutsch' => 'de_DE'],
        ]);
        $item = $form->get('items')->get(0);

        self::assertNull($item->get('image')->getData());
        self::assertNull($item->get('image')->getViewData());
        self::assertSame('cardnext/homepage/industry.webp', $item->get('existingImage')->getData());
        self::assertSame($configuration, $block->getConfiguration());
    }

    /** @param array<string, mixed> $configuration */
    private function block(string $type, array $configuration): CmsBlock
    {
        $block = new CmsBlock();
        $block->setLocale('de_DE');
        $block->setType($type);
        $block->setConfiguration($configuration);

        return $block;
    }
}
