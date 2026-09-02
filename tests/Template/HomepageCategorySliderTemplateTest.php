<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class HomepageCategorySliderTemplateTest extends TestCase
{
    public function testCategoriesRemainInTheirExistingOrderAndLinksAreSlides(): void
    {
        $homepage = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/homepage/index.html.twig');
        $offset = 0;

        foreach (['card_printers', 'rfid_readers', 'plastic_cards', 'id_accessories', 'ribbons', 'barcode_scanners', 'access_control'] as $code) {
            $position = strpos($homepage, "'code': '" . $code . "'", $offset);
            self::assertNotFalse($position, sprintf('Category "%s" is missing or out of order.', $code));
            $offset = $position + 1;
        }

        self::assertSame(7, substr_count($homepage, "{'code': '"));
        self::assertStringContainsString('data-cn-category-slider', $homepage);
        self::assertStringContainsString('data-cn-category-slider-viewport', $homepage);
        self::assertStringContainsString('cn-category-slider__track', $homepage);
        self::assertStringContainsString('cn-category-slider__slide', $homepage);
        self::assertMatchesRegularExpression('/cn-category-slider__slide.*cn-home-category.*sylius_shop_product_index/s', $homepage);
    }

    public function testSliderHasAccessibleControlsAndNativeFourCardScrolling(): void
    {
        $homepage = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/homepage/index.html.twig');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../../assets/shop/styles/cardnext.css');
        $javascript = (string) file_get_contents(__DIR__ . '/../../assets/shop/homepage-product-slider.js');

        self::assertStringContainsString('data-cn-category-slider-previous', $homepage);
        self::assertStringContainsString('data-cn-category-slider-next', $homepage);
        self::assertStringContainsString('cardnext.storefront.homepage.categories.previous', $homepage);
        self::assertStringContainsString('cardnext.storefront.homepage.categories.next', $homepage);
        self::assertMatchesRegularExpression('/\.cn-category-slider__track \{[^}]*display: flex;[^}]*gap:/s', $stylesheet);
        self::assertMatchesRegularExpression('/\.cn-category-slider__slide \{[^}]*flex: 0 0 calc\(\(100% - \(3 \* var\(--cn-category-slider-gap\)\)\) \/ 4\);[^}]*scroll-snap-align: start;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\.cn-category-slider__viewport \{[^}]*overflow-x: auto;[^}]*scroll-snap-type: x mandatory;[^}]*scrollbar-width: none;/s', $stylesheet);
        self::assertStringContainsString("slide: '.cn-category-slider__slide'", $javascript);
        self::assertStringContainsString('getBoundingClientRect().width + gap', $javascript);
        self::assertStringNotContainsString('autoplay', strtolower($javascript));
        self::assertStringNotContainsString('cloneNode', $javascript);
    }

    public function testCategoryControlTranslationsExistInEveryStorefrontLocale(): void
    {
        foreach (['da_DK', 'de', 'de_AT', 'es_ES', 'it_IT', 'nl_NL', 'sv_SE'] as $locale) {
            $translations = (string) file_get_contents(sprintf('%s/../../translations/messages.%s.yaml', __DIR__, $locale));

            self::assertMatchesRegularExpression('/categories:\s+title:.*\s+previous: .+\s+next: .+/', $translations, $locale);
        }
    }
}
