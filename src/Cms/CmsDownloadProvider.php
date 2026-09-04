<?php

declare(strict_types=1);

namespace App\Cms;

use App\Entity\Cms\CmsDownload;
use App\Repository\Cms\CmsDownloadRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CmsDownloadProvider
{
    public function __construct(
        private readonly CmsDownloadRepository $repository,
        private readonly ChannelContextInterface $channels,
        private readonly LocaleContextInterface $locales,
        private readonly RequestStack $requests,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{downloads: list<CmsDownload>, groups: array<string, list<CmsDownload>>, manufacturers: list<string>, filters: array{q: string, type: string, manufacturer: string, os: string}, active: bool}
     */
    public function downloadCenter(array $config = []): array
    {
        $channel = $this->channels->getChannel();
        $locale = $this->locales->getLocaleCode();
        $query = $this->requests->getCurrentRequest()?->query;
        $configuredTypes = array_values(array_intersect(CmsDownload::TYPES, (array) ($config['types'] ?? [])));
        $requestedType = $query?->getString('type') ?? '';

        $filters = [
            'q' => trim($query?->getString('q') ?? ''),
            // A visitor's selection can narrow, but never widen, the block configuration.
            'type' => '' === $requestedType || ([] !== $configuredTypes && !in_array($requestedType, $configuredTypes, true)) ? '' : $requestedType,
            'manufacturer' => $query?->getString('manufacturer') ?: (string) ($config['manufacturer'] ?? ''),
            'os' => $query?->getString('os') ?? '',
            'types' => $configuredTypes,
        ];

        $downloads = $this->repository->findVisible($channel, $locale, $filters, isset($config['limit']) ? (int) $config['limit'] : null);
        $groups = [];
        foreach ($downloads as $download) {
            $groups[$download->getManufacturer()][] = $download;
        }

        $publicFilters = [
            'q' => $filters['q'],
            'type' => $filters['type'],
            'manufacturer' => $filters['manufacturer'],
            'os' => $filters['os'],
        ];

        return [
            'downloads' => $downloads,
            'groups' => $groups,
            'manufacturers' => $this->repository->findVisibleManufacturers($channel, $locale, $configuredTypes, (string) ($config['manufacturer'] ?? '')),
            'filters' => $publicFilters,
            'active' => [] !== array_filter($publicFilters, static fn (string $value): bool => '' !== $value),
        ];
    }
}
