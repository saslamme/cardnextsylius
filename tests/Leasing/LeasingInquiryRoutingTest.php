<?php

declare(strict_types=1);

namespace App\Tests\Leasing;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class LeasingInquiryRoutingTest extends KernelTestCase
{
    private const INQUIRY_PATH = '/leasing/anfrage/ZEBRA_ZC35000C000EM00';

    public function testLeasingInquiryRouteTakesPrecedenceOverConfiguratorFallbackForGetAndPost(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        foreach (['GET', 'POST'] as $method) {
            $router->getContext()->setMethod($method);
            $match = $router->match(self::INQUIRY_PATH);

            self::assertSame('cardnext_shop_leasing_inquiry', $match['_route']);
            self::assertNotSame('cardnext_shop_configurator_page', $match['_route']);
            self::assertSame('ZEBRA_ZC35000C000EM00', $match['code']);
        }
    }

    public function testConfiguratorAndCmsPathsStillUseTheCatchAllRoute(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $router->getContext()->setMethod('GET');

        foreach (['/konfigurator/kartendesign', '/service/support'] as $path) {
            $match = $router->match($path);

            self::assertSame('cardnext_shop_configurator_page', $match['_route']);
            self::assertSame(ltrim($path, '/'), $match['configuratorPath']);
        }
    }
}
