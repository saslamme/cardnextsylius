<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Controller\Admin\CmsDownloadAdminController;
use App\Entity\Cms\CmsDownload;
use App\Repository\Cms\CmsDownloadRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class CmsDownloadRegressionTest extends TestCase
{
    public function testRepositoryUsesDoctrineCompatibleIndividualParameters(): void
    {
        $source = file_get_contents((new \ReflectionClass(CmsDownloadRepository::class))->getFileName());
        self::assertIsString($source);
        self::assertStringNotContainsString('setParameters(', $source);
        self::assertStringContainsString("setParameter('channel'", $source);
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
}
