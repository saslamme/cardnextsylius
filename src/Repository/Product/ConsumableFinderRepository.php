<?php

declare(strict_types=1);

namespace App\Repository\Product;

use App\Entity\Product\DeviceModel;
use App\Entity\Product\Product;
use App\Entity\Product\ProductDeviceCompatibility;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class ConsumableFinderRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return list<DeviceModel> */
    public function findPublicDevices(): array
    {
        // @phpstan-ignore return.type
        return $this->entityManager->createQueryBuilder()
            ->select('DISTINCT device', 'manufacturer', 'aliases')
            ->from(DeviceModel::class, 'device')
            ->join('device.manufacturer', 'manufacturer')
            ->leftJoin('device.aliases', 'aliases')
            ->andWhere('manufacturer.enabled = true')
            ->orderBy('manufacturer.name', 'ASC')->addOrderBy('device.name', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<ProductDeviceCompatibility> */
    public function findAvailableCompatibilities(DeviceModel $device, ChannelInterface $channel, ?string $type = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('compatibility', 'product', 'variant', 'pricing', 'images')
            ->from(ProductDeviceCompatibility::class, 'compatibility')
            ->join('compatibility.product', 'product', 'WITH', 'product.enabled = true')
            ->join('product.channels', 'channel', 'WITH', 'channel = :channel')
            ->join('product.variants', 'variant', 'WITH', 'variant.enabled = true')
            ->join('variant.channelPricings', 'pricing', 'WITH', 'pricing.channelCode = :channelCode AND pricing.price IS NOT NULL')
            ->leftJoin('product.images', 'images')
            ->andWhere('compatibility.deviceModel = :device')->andWhere('compatibility.enabled = true')
            ->andWhere('compatibility.compatibilityType IN (:finderTypes)')
            ->orderBy('compatibility.position', 'ASC')->addOrderBy('product.code', 'ASC')
            ->setParameter('device', $device)->setParameter('channel', $channel)
            ->setParameter('channelCode', $channel->getCode())
            ->setParameter('finderTypes', $type === null ? self::finderTypes() : [$type]);

        // @phpstan-ignore return.type
        return $qb->getQuery()->getResult();
    }

    public function findDeviceBySlug(string $slug): ?DeviceModel
    {
        // @phpstan-ignore return.type
        return $this->entityManager->createQueryBuilder()->select('device', 'manufacturer', 'aliases')
            ->from(DeviceModel::class, 'device')->join('device.manufacturer', 'manufacturer')
            ->leftJoin('device.aliases', 'aliases')->andWhere('device.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<ProductDeviceCompatibility> */
    public function findEnabledForProduct(Product $product): array
    {
        // @phpstan-ignore return.type
        return $this->entityManager->createQueryBuilder()->select('compatibility', 'device', 'manufacturer')
            ->from(ProductDeviceCompatibility::class, 'compatibility')
            ->join('compatibility.deviceModel', 'device')->join('device.manufacturer', 'manufacturer')
            ->andWhere('compatibility.product = :product')->andWhere('compatibility.enabled = true')
            ->orderBy('manufacturer.name', 'ASC')->addOrderBy('device.name', 'ASC')
            ->setParameter('product', $product)->getQuery()->getResult();
    }

    public function findByLinkedProduct(Product $product): ?DeviceModel
    {
        return $this->entityManager->getRepository(DeviceModel::class)->findOneBy(['linkedProduct' => $product]);
    }

    /** @return list<string> */
    public static function finderTypes(): array
    {
        return [ProductDeviceCompatibility::TYPE_CONSUMABLE_FOR, ProductDeviceCompatibility::TYPE_CLEANING_FOR, ProductDeviceCompatibility::TYPE_ACCESSORY_FOR];
    }
}
