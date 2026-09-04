<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Controller\Admin\CmsDownloadAdminController;
use App\Cms\CmsDownloadProvider;
use App\Entity\Cms\CmsDownload;
use App\Entity\Cms\CmsDownloadTranslation;
use App\Form\Cms\CmsDownloadType;
use App\Repository\Cms\CmsDownloadRepository;
use App\Twig\CmsExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

final class CmsDownloadRegressionTest extends KernelTestCase
{
    public function testEmptyTranslationTitlesAreRemovedWhenSubmittingAnUpload(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);

        $download = new CmsDownload();
        $download->setFilePath('downloads/existing.pdf');
        $download->addTranslation($this->translation('de_DE', 'Handbuch'));
        $download->addTranslation($this->translation('en_US', ''));
        $download->addTranslation($this->translation('fr_FR', 'Ancien titre'));

        $uploadPath = tempnam(sys_get_temp_dir(), 'cms-download-');
        self::assertNotFalse($uploadPath);
        file_put_contents($uploadPath, '%PDF-1.4 test');

        $form = $formFactory->create(CmsDownloadType::class, $download);
        $form->submit([
            'code' => 'printer-manual',
            'type' => 'manual',
            'manufacturer' => 'Cardnext',
            'productFamily' => '',
            'version' => '',
            'operatingSystems' => [],
            'position' => '100',
            'enabled' => '1',
            'publishedAt' => '',
            'channels' => [],
            'products' => [],
            'uploadedFile' => new UploadedFile($uploadPath, 'manual.pdf', 'application/pdf', null, true),
            'externalUrl' => '',
            'translations' => [
                ['locale' => 'de_DE', 'title' => 'Handbuch', 'description' => 'Beschreibung'],
                ['locale' => 'en_US', 'title' => '', 'description' => 'May remain nullable'],
                ['locale' => 'fr_FR', 'title' => '', 'description' => ''],
            ],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertCount(1, $download->getTranslations());
        self::assertSame('Handbuch', $download->getTranslation('de_DE')?->getTitle());
        self::assertNull($download->getTranslation('en_US'));
        self::assertNull($download->getTranslation('fr_FR'));
        self::assertInstanceOf(UploadedFile::class, $form->get('uploadedFile')->getData());
    }

    public function testDoctrineEntitiesRequiredForLazyLoadingAreNotFinal(): void
    {
        self::assertFalse((new \ReflectionClass(CmsDownload::class))->isFinal());
        self::assertFalse((new \ReflectionClass(CmsDownloadTranslation::class))->isFinal());
    }

    public function testRepositoryUsesDoctrineCompatibleIndividualParameters(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsDownloadRepository::class))->getFileName());
        self::assertIsString($source);
        self::assertStringNotContainsString('setParameters(', $source);
        self::assertStringContainsString("setParameter('channel'", $source);
    }

    public function testStorefrontRepositoryAppliesEveryVisibilityAndVisitorFilter(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsDownloadRepository::class))->getFileName());
        self::assertIsString($source);

        self::assertStringContainsString("'c = :channel'", $source);
        self::assertStringContainsString("'t.locale = :locale'", $source);
        self::assertStringContainsString("'d.enabled = true'", $source);
        self::assertStringContainsString("'d.publishedAt IS NULL OR d.publishedAt <= :now'", $source);
        self::assertStringContainsString('LOWER(t.title) LIKE :q', $source);
        self::assertStringContainsString("'d.manufacturer = :manufacturer'", $source);
        self::assertStringContainsString("'d.operatingSystems LIKE :os'", $source);
        self::assertStringContainsString("'d.type = :type'", $source);
    }

    public function testProductDownloadsReuseVisibilityRulesAndRestrictTheProduct(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsDownloadRepository::class))->getFileName());
        self::assertIsString($source);

        self::assertStringContainsString('function findVisibleForProduct(', $source);
        self::assertStringContainsString('visibleQueryBuilder($channel, $locale)', $source);
        self::assertStringContainsString("innerJoin('d.products', 'p')", $source);
        self::assertStringContainsString("'p = :product'", $source);
        self::assertStringContainsString('->addSelect(\'t\')', $source);
        self::assertStringContainsString('->distinct()', $source);
        self::assertStringContainsString("orderBy('d.position', 'ASC')", $source);
        self::assertStringContainsString("'TRIM(t.title) <> :emptyTitle'", $source);
    }

    public function testProductProviderResolvesChannelAndLocaleContexts(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsDownloadProvider::class))->getFileName());
        self::assertIsString($source);

        self::assertStringContainsString('function forProduct(Product $product): array', $source);
        self::assertStringContainsString('$this->channels->getChannel()', $source);
        self::assertStringContainsString('$this->locales->getLocaleCode()', $source);
        self::assertStringContainsString('findVisibleForProduct(', $source);
    }

    public function testTwigExposesProductDownloadsThroughTheProvider(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsExtension::class))->getFileName());
        self::assertIsString($source);

        self::assertStringContainsString("new TwigFunction('cardnext_cms_product_downloads', [\$this->downloads, 'forProduct'])", $source);
    }

    public function testProductTemplateCombinesCmsAndLegacyDownloadsInOnePanel(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/templates/bundles/SyliusShopBundle/product/show/content/product_listing.html.twig');
        self::assertIsString($source);

        self::assertStringContainsString('product.getPublicDocuments(sylius.localeCode)', $source);
        self::assertStringContainsString('cardnext_cms_product_downloads(product)', $source);
        self::assertStringContainsString('documents|length > 0 or cmsDownloads|length > 0', $source);
        self::assertSame(2, substr_count($source, '{% if hasDownloads %}'));
        self::assertSame(1, substr_count($source, 'id="tab-downloads"'));
        self::assertSame(1, substr_count($source, 'id="downloads"'));
        self::assertLessThan(strpos($source, '{% for document in documents %}'), strpos($source, '{% for download in cmsDownloads %}'));
        self::assertStringContainsString("path('cardnext_cms_download_file', {id: download.id})", $source);
        self::assertStringNotContainsString('download.filePath', $source);
    }

    public function testConfiguredDownloadTypesUseAnInRestrictionThatVisitorFiltersCannotReplace(): void
    {
        $repositorySource = file_get_contents((new \ReflectionClass(CmsDownloadRepository::class))->getFileName());
        $providerSource = file_get_contents((new \ReflectionClass(CmsDownloadProvider::class))->getFileName());
        self::assertIsString($repositorySource);
        self::assertIsString($providerSource);

        self::assertStringContainsString("'d.type IN (:types)'", $repositorySource);
        self::assertStringContainsString("'types' => \$configuredTypes", $providerSource);
        self::assertStringContainsString('!in_array($requestedType, $configuredTypes, true)', $providerSource);
        self::assertStringNotContainsString("\$config['types'][0]", $providerSource);
    }

    public function testManufacturerOptionsHaveTheirOwnChannelAndLocaleAwareQuery(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsDownloadRepository::class))->getFileName());
        self::assertIsString($source);

        self::assertStringContainsString('function findVisibleManufacturers(', $source);
        self::assertStringContainsString("'DISTINCT d.manufacturer AS manufacturer'", $source);
        self::assertStringContainsString('visibleQueryBuilder($channel, $locale)', $source);
        self::assertStringContainsString("orderBy('d.manufacturer', 'ASC')", $source);
    }

    public function testEntityValidatesHttpsAndExactlyOneSource(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsDownload::class))->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString("parse_url(\$this->externalUrl,PHP_URL_SCHEME)!=='https'", $source);
        self::assertStringContainsString('($this->filePath===null)===($this->externalUrl===null)', $source);
    }

    public function testAdminCrudRoutesAndDeleteMethodAreDeclared(): void
    {
        $routes = [];
        foreach ((new \ReflectionClass(CmsDownloadAdminController::class))->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $routes[$route->getName()] = $route->getMethods();
            }
        }
        self::assertSame(['GET', 'POST'], $routes['cardnext_admin_cms_download_new']);
        self::assertSame(['GET', 'POST'], $routes['cardnext_admin_cms_download_edit']);
        self::assertSame(['POST'], $routes['cardnext_admin_cms_download_delete']);
    }

    public function testAdminTemplatesUseTheSharedCardnextLayout(): void
    {
        $templateDirectory = dirname(__DIR__, 2) . '/templates/admin/cardnext/cms/download';

        foreach (['index.html.twig', 'form.html.twig'] as $template) {
            $source = file_get_contents($templateDirectory . '/' . $template);
            self::assertIsString($source);
            self::assertStringContainsString("{% extends 'admin/cardnext/layout.html.twig' %}", $source);
            self::assertStringNotContainsString('sylius_admin.common.index.content', $source);
        }

        $formSource = file_get_contents($templateDirectory . '/form.html.twig');
        self::assertIsString($formSource);
        self::assertStringContainsString("{% form_theme form '@SyliusAdmin/shared/form_theme.html.twig' %}", $formSource);
    }

    private function translation(string $locale, string $title): CmsDownloadTranslation
    {
        $translation = new CmsDownloadTranslation();
        $translation->setLocale($locale);
        $translation->setTitle($title);

        return $translation;
    }
}
