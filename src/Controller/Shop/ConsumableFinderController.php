<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Product\DeviceModel;
use App\Entity\Product\ProductDeviceCompatibility;
use App\Repository\Product\ConsumableFinderRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConsumableFinderController extends AbstractController
{
    #[Route('/verbrauchsmaterial-finder', name: 'cardnext_shop_consumable_finder', methods: ['GET'], priority: 200)]
    public function __invoke(Request $request, ChannelContextInterface $channelContext, ConsumableFinderRepository $repository): Response
    {
        $channel = $channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            throw $this->createNotFoundException();
        }

        $devices = $repository->findPublicDevices();
        usort($devices, static fn (DeviceModel $left, DeviceModel $right): int => strcasecmp($left->getManufacturer()->getName(), $right->getManufacturer()->getName()) ?: strnatcasecmp($left->getName(), $right->getName()));
        $deviceSlug = trim((string) $request->query->get('device', ''));
        $device = $deviceSlug !== '' ? $repository->findDeviceBySlug($deviceSlug) : null;
        $typeKey = (string) $request->query->get('type', 'all');
        $typeMap = ['consumable' => ProductDeviceCompatibility::TYPE_CONSUMABLE_FOR, 'cleaning' => ProductDeviceCompatibility::TYPE_CLEANING_FOR, 'accessory' => ProductDeviceCompatibility::TYPE_ACCESSORY_FOR];
        $typeKey = isset($typeMap[$typeKey]) ? $typeKey : 'all';
        $relations = $device === null ? [] : $repository->findAvailableCompatibilities($device, $channel, $typeMap[$typeKey] ?? null);
        $groups = ['consumable_for' => [], 'cleaning_for' => [], 'accessory_for' => []];
        foreach ($relations as $relation) {
            $groups[$relation->getCompatibilityType()][] = $relation;
        }

        return $this->render('shop/consumable_finder/index.html.twig', compact('devices', 'device', 'deviceSlug', 'typeKey', 'groups'));
    }
}
