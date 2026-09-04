<?php

declare(strict_types=1);

namespace App\Cms;

use App\Entity\Product\Manufacturer;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CmsManufacturerSliderResolver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<mixed> $manufacturerCodes
     *
     * @return list<Manufacturer>
     */
    public function resolve(array $manufacturerCodes, ?int $limit = null): array
    {
        $limit = max(1, min(24, $limit ?? 12));
        $codes = [];
        foreach ($manufacturerCodes as $code) {
            if (is_string($code) && ($code = trim($code)) !== '' && !isset($codes[$code])) {
                $codes[$code] = true;
            }
        }
        if ($codes === []) {
            return [];
        }

        /** @var list<Manufacturer> $manufacturers */
        $manufacturers = $this->entityManager->getRepository(Manufacturer::class)->createQueryBuilder('manufacturer')
            ->andWhere('manufacturer.code IN (:codes)')
            ->andWhere('manufacturer.enabled = :enabled')
            ->setParameter('codes', array_keys($codes))
            ->setParameter('enabled', true)
            ->getQuery()->getResult();
        $byCode = [];
        foreach ($manufacturers as $manufacturer) {
            $byCode[$manufacturer->getCode()] = $manufacturer;
        }

        $ordered = [];
        foreach (array_keys($codes) as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
            }
            if (count($ordered) === $limit) {
                break;
            }
        }

        return $ordered;
    }
}
