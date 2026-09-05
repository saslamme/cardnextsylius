<?php

declare(strict_types=1);

namespace App\Launch;

use App\Cms\CmsSlug;
use App\Entity\Cms\CmsPage;
use App\Entity\Channel\Channel;
use App\Entity\Content\LegalPage;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class LaunchCheckRunner
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private string $projectDir,
    ) {
    }

    public function run(): LaunchCheckResult
    {
        $result = new LaunchCheckResult();
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable $exception) {
            $result->issue('critical', 'database', 'DATABASE_UNAVAILABLE', 'Doctrine could not query the database.', ['error' => $exception->getMessage()]);

            return $result;
        }

        $this->checkRoutes($result);
        $this->checkChannels($result);
        $this->checkProducts($result);
        $this->checkCms($result);
        $this->checkLegalPages($result);
        $this->checkPublicSlugCollisions($result);

        return $result;
    }

    private function checkRoutes(LaunchCheckResult $result): void
    {
        foreach (['sylius_shop_homepage', 'sylius_shop_product_index', 'sylius_shop_product_show', 'cardnext_shop_legal_imprint', 'cardnext_shop_legal_privacy', 'cardnext_shop_legal_terms'] as $name) {
            if ($this->router->getRouteCollection()->get($name) === null) {
                $result->issue('critical', 'routing', 'ROUTE_MISSING', sprintf('Required storefront route "%s" is not registered.', $name), ['route' => $name]);
            }
        }
    }

    private function checkChannels(LaunchCheckResult $result): void
    {
        /** @var list<Channel> $channels */
        $channels = $this->entityManager->getRepository(Channel::class)->findBy(['enabled' => true]);
        if ($channels === []) {
            $result->issue('critical', 'channel', 'NO_ENABLED_CHANNEL', 'No enabled sales channel exists.');
        }
        foreach ($channels as $channel) {
            $context = ['channel_code' => (string) $channel->getCode()];
            if ($channel->getHostname() === null || trim($channel->getHostname()) === '') {
                $result->issue('critical', 'channel', 'CHANNEL_HOSTNAME_MISSING', 'Enabled channel has no hostname.', $context);
            }
            if ($channel->getBaseCurrency() === null || $channel->getDefaultLocale() === null || $channel->getLocales()->isEmpty()) {
                $result->issue('critical', 'channel', 'CHANNEL_MARKET_INCOMPLETE', 'Enabled channel needs a base currency, default locale and at least one locale.', $context);
            }
            foreach ([$channel->getLogoPath(), $channel->getLogoDarkPath(), $channel->getFaviconPath()] as $path) {
                if ($path !== null && !$this->publicAssetExists($path)) {
                    $result->issue('warning', 'asset', 'CHANNEL_ASSET_MISSING', sprintf('Channel asset "%s" does not exist below public/.', $path), $context + ['path' => $path]);
                }
            }
        }
    }

    private function checkProducts(LaunchCheckResult $result): void
    {
        /** @var list<Product> $products */
        $products = $this->entityManager->getRepository(Product::class)->findBy(['enabled' => true]);
        foreach ($products as $product) {
            $context = ['product_code' => (string) $product->getCode()];
            $enabledVariants = array_filter($product->getVariants()->toArray(), static fn (mixed $variant): bool => $variant instanceof ProductVariant && $variant->isEnabled());
            if ($enabledVariants === []) {
                $result->issue('critical', 'product', 'PRODUCT_NO_ENABLED_VARIANT', sprintf('Product "%s" has no enabled variant.', $product->getCode()), $context);
            }
            if ($product->getImages()->isEmpty()) {
                $result->issue('warning', 'asset', 'PRODUCT_IMAGE_MISSING', sprintf('Product "%s" has no image.', $product->getCode()), $context);
            }
            foreach ($product->getImages() as $image) {
                $path = $image->getPath();
                if (is_string($path) && $path !== '' && !$this->publicAssetExists($path)) {
                    $result->issue('critical', 'asset', 'PRODUCT_IMAGE_FILE_MISSING', sprintf('Image file "%s" for product "%s" is missing.', $path, $product->getCode()), $context + ['path' => $path]);
                }
            }
            if ($product->getTranslations()->isEmpty()) {
                $result->issue('critical', 'product', 'PRODUCT_TRANSLATION_MISSING', sprintf('Product "%s" has no translation.', $product->getCode()), $context);
            }
        }
    }

    private function checkCms(LaunchCheckResult $result): void
    {
        /** @var list<CmsPage> $pages */
        $pages = $this->entityManager->getRepository(CmsPage::class)->findBy(['status' => CmsPage::STATUS_PUBLISHED]);
        $claimedSlugs = [];
        foreach ($pages as $page) {
            $context = ['cms_code' => $page->getCode()];
            if ($page->getChannels()->isEmpty()) {
                $result->issue('critical', 'cms', 'CMS_PAGE_WITHOUT_CHANNEL', sprintf('Published CMS page "%s" has no channel.', $page->getCode()), $context);
            }
            if ($page->getTranslations()->isEmpty()) {
                $result->issue('critical', 'cms', 'CMS_PAGE_WITHOUT_TRANSLATION', sprintf('Published CMS page "%s" has no translation.', $page->getCode()), $context);
            }
            foreach ($page->getTranslations() as $translation) {
                if (!CmsSlug::isSafe($translation->getSlug())) {
                    $result->issue('critical', 'cms', 'CMS_SLUG_UNSAFE', sprintf('CMS page "%s" has an empty, unsafe or reserved slug.', $page->getCode()), $context + ['locale' => $translation->getLocale(), 'slug' => $translation->getSlug()]);
                }
                foreach ($page->getChannels() as $channel) {
                    $key = $channel->getId() . '|' . $translation->getLocale() . '|' . $translation->getSlug();
                    if (isset($claimedSlugs[$key])) {
                        $result->issue('critical', 'routing', 'CMS_SLUG_COLLISION', sprintf('CMS pages "%s" and "%s" share a public slug.', $claimedSlugs[$key], $page->getCode()), $context + ['other_cms_code' => $claimedSlugs[$key], 'locale' => $translation->getLocale(), 'slug' => $translation->getSlug()]);
                    }
                    $claimedSlugs[$key] = $page->getCode();
                }
            }
        }
    }

    private function checkLegalPages(LaunchCheckResult $result): void
    {
        /** @var list<Channel> $channels */
        $channels = $this->entityManager->getRepository(Channel::class)->findBy(['enabled' => true]);
        /** @var list<LegalPage> $pages */
        $pages = $this->entityManager->getRepository(LegalPage::class)->findAll();
        foreach ($channels as $channel) {
            foreach (['imprint', 'privacy', 'terms'] as $code) {
                $found = false;
                foreach ($pages as $page) {
                    if ($page->getCode() === $code && $page->getChannels()->contains($channel) && trim($page->getContent()) !== '') {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $result->issue('critical', 'legal', 'LEGAL_PAGE_MISSING', sprintf('Channel "%s" has no non-empty "%s" legal page.', $channel->getCode(), $code), ['channel_code' => (string) $channel->getCode(), 'legal_code' => $code]);
                }
            }
        }
    }

    private function checkPublicSlugCollisions(LaunchCheckResult $result): void
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT pt.locale, pt.slug, p.code AS product_code, t.code AS taxon_code
            FROM sylius_product_translation pt
            INNER JOIN sylius_product p ON p.id = pt.translatable_id
            INNER JOIN sylius_taxon_translation tt ON tt.locale = pt.locale AND tt.slug = pt.slug
            INNER JOIN sylius_taxon t ON t.id = tt.translatable_id
            ORDER BY pt.locale, pt.slug
            SQL);
        foreach ($rows as $row) {
            $locale = is_string($row['locale'] ?? null) ? $row['locale'] : '';
            $slug = is_string($row['slug'] ?? null) ? $row['slug'] : '';
            $productCode = is_string($row['product_code'] ?? null) ? $row['product_code'] : '';
            $taxonCode = is_string($row['taxon_code'] ?? null) ? $row['taxon_code'] : '';
            $result->issue('critical', 'routing', 'PUBLIC_SLUG_COLLISION', sprintf('Product "%s" and taxon "%s" share public slug "%s".', $productCode, $taxonCode, $slug), ['locale' => $locale, 'slug' => $slug, 'product_code' => $productCode, 'taxon_code' => $taxonCode]);
        }

        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT c.code AS channel_code, ct.locale, ct.slug, cp.code AS cms_code, p.code AS product_code
            FROM cardnext_cms_page cp
            INNER JOIN cardnext_cms_page_translation ct ON ct.page_id = cp.id
            INNER JOIN cardnext_cms_page_channel cc ON cc.cms_page_id = cp.id
            INNER JOIN sylius_channel c ON c.id = cc.channel_id
            INNER JOIN sylius_product_channels pc ON pc.channel_id = c.id
            INNER JOIN sylius_product p ON p.id = pc.product_id AND p.enabled = 1
            INNER JOIN sylius_product_translation pt ON pt.translatable_id = p.id AND pt.locale = ct.locale AND pt.slug = ct.slug
            WHERE cp.status = 'published'
            ORDER BY c.code, ct.locale, ct.slug
            SQL);
        foreach ($rows as $row) {
            $context = [];
            foreach (['channel_code', 'locale', 'slug', 'cms_code', 'product_code'] as $key) {
                $context[$key] = is_string($row[$key] ?? null) ? $row[$key] : '';
            }
            $result->issue('critical', 'routing', 'CMS_PRODUCT_SLUG_COLLISION', sprintf('CMS page "%s" and product "%s" share public slug "%s" in channel "%s".', $context['cms_code'], $context['product_code'], $context['slug'], $context['channel_code']), $context);
        }
    }

    private function publicAssetExists(string $path): bool
    {
        $path = parse_url($path, PHP_URL_PATH);

        return is_string($path) && is_file($this->projectDir . '/public/' . ltrim($path, '/'));
    }
}
