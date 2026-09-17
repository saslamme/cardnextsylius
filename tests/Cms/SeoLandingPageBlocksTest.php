<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Cms\CmsBlockRendererRegistry;
use App\Entity\Cms\CmsBlock;
use App\Entity\Cms\CmsPage;
use App\Entity\Seo\SeoLandingPage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class SeoLandingPageBlocksTest extends TestCase
{
    public function testCmsPageOwnerRemainsValid(): void
    {
        $block = new CmsBlock();
        (new CmsPage())->addBlock($block);
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($block));
        self::assertSame(CmsBlock::PLACEMENT_CONTENT, $block->getPlacement());
    }

    public function testSeoLandingPageOwnerIsValidAndClearsCmsOwner(): void
    {
        $block = new CmsBlock();
        $cmsPage = new CmsPage();
        $cmsPage->addBlock($block);
        $landingPage = new SeoLandingPage();
        $landingPage->addBlock($block);
        self::assertNull($block->getPage());
        self::assertSame($landingPage, $block->getSeoLandingPage());
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($block));
    }

    public function testExactlyOneOwnerIsRequired(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $block = new CmsBlock();
        self::assertGreaterThan(0, $validator->validate($block)->count());
        $block->setPage(new CmsPage());
        $block->setSeoLandingPage(new SeoLandingPage());
        self::assertGreaterThan(0, $validator->validate($block)->count());
    }

    public function testSeoAllowlistExcludesHomepageAndHeroTypes(): void
    {
        foreach (['hero', 'category_slider', 'homepage_service', 'homepage_industries', 'homepage_promo'] as $type) {
            self::assertNotContains($type, CmsBlockRendererRegistry::SEO_LANDING_PAGE_TYPES);
        }
        self::assertContains('rich_text', CmsBlockRendererRegistry::SEO_LANDING_PAGE_TYPES);
    }

    public function testBlocksRetainPositionOrderWithinPlacements(): void
    {
        $page = new SeoLandingPage();
        foreach ([[20, CmsBlock::PLACEMENT_BEFORE_CATALOG], [10, CmsBlock::PLACEMENT_AFTER_CATALOG], [10, CmsBlock::PLACEMENT_BEFORE_CATALOG]] as [$position, $placement]) {
            $block = new CmsBlock(); $block->setPosition($position); $block->setPlacement($placement); $page->addBlock($block);
        }
        $before = array_filter($page->getBlocks()->toArray(), static fn (CmsBlock $block): bool => $block->getPlacement() === CmsBlock::PLACEMENT_BEFORE_CATALOG);
        usort($before, static fn (CmsBlock $a, CmsBlock $b): int => $a->getPosition() <=> $b->getPosition());
        self::assertSame([10, 20], array_map(static fn (CmsBlock $block): int => $block->getPosition(), $before));
    }
}
