<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Addressing\Zone;
use App\Entity\Channel\Channel;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Shipping\ShippingMethod;
use App\International\CardnextMarketRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class MarketOverviewAdminController extends AbstractController
{
    #[Route('/admin/cardnext/markets', name: 'cardnext_admin_market_overview', methods: ['GET'])]
    public function index(
        CardnextMarketRegistry $markets,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var PaymentMethod|null $vorkasse */
        $vorkasse = $entityManager->getRepository(PaymentMethod::class)->findOneBy([
            'code' => 'VORKASSE',
        ]);

        $rows = [];

        foreach ($markets->all() as $market) {
            /** @var Channel|null $channel */
            $channel = $entityManager->getRepository(Channel::class)->findOneBy([
                'code' => $market->channelCode,
            ]);

            /** @var Zone|null $shippingZone */
            $shippingZone = $entityManager->getRepository(Zone::class)->findOneBy([
                'code' => $market->countryCode . '_SHIPPING',
            ]);

            /** @var Zone|null $taxZone */
            $taxZone = $entityManager->getRepository(Zone::class)->findOneBy([
                'code' => $market->countryCode . '_TAX',
            ]);

            /** @var ShippingMethod|null $shippingMethod */
            $shippingMethod = $entityManager->getRepository(ShippingMethod::class)->findOneBy([
                'code' => $market->countryCode . '_STANDARD',
            ]);

            $countryAssigned = false;
            if ($channel instanceof Channel) {
                foreach ($channel->getCountries() as $country) {
                    if ($country->getCode() === $market->countryCode) {
                        $countryAssigned = true;

                        break;
                    }
                }
            }

            $shippingAmount = null;
            if ($shippingMethod instanceof ShippingMethod) {
                // @phpstan-ignore offsetAccess.nonOffsetAccessible
                $shippingAmount = $shippingMethod->getConfiguration()[$market->channelCode]['amount'] ?? null;
            }

            $rows[] = [
                'definition' => $market,
                'channel' => $channel,
                'country_assigned' => $countryAssigned,
                'core_ready' => $channel instanceof Channel &&
                    $channel->getHostname() === $market->hostname &&
                    $channel->getDefaultLocale()?->getCode() === $market->localeCode &&
                    $channel->getBaseCurrency()?->getCode() === $market->currencyCode &&
                    $countryAssigned,
                'shipping_zone' => $shippingZone,
                'tax_zone' => $taxZone,
                'shipping_method' => $shippingMethod,
                'shipping_amount' => $shippingAmount,
                'vorkasse_assigned' => $channel instanceof Channel &&
                    $vorkasse instanceof PaymentMethod &&
                    $vorkasse->hasChannel($channel),
            ];
        }

        return $this->render('admin/cardnext/market_overview/index.html.twig', [
            'page_title' => 'Cardnext Märkte',
            'markets' => $rows,
        ]);
    }
}
