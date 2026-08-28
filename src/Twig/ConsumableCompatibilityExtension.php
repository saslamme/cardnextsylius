<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product\Product;
use App\Repository\Product\ConsumableFinderRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ConsumableCompatibilityExtension extends AbstractExtension
{
    public function __construct(private readonly ConsumableFinderRepository $repository)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_device_groups', $this->deviceGroups(...)), new TwigFunction('cardnext_linked_device', $this->repository->findByLinkedProduct(...))];
    }

    /** @return array<string, array{name: string, devices: list<object>}> */
    public function deviceGroups(Product $product): array
    {
        $groups = [];
        foreach ($this->repository->findEnabledForProduct($product) as $relation) {
            $device = $relation->getDeviceModel();
            $key = $device->getManufacturer()->getCode();
            $groups[$key] ??= ['name' => $device->getManufacturer()->getName(), 'devices' => []];
            $groups[$key]['devices'][] = $device;
        }

        return $groups;
    }
}
