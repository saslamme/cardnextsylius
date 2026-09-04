<?php

declare(strict_types=1);

namespace App\Twig;

use App\Cms\CmsBlockRendererRegistry;
use App\Cms\CmsDownloadProvider;
use App\Cms\CmsMenuResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CmsExtension extends AbstractExtension
{
    public function __construct(
        private readonly CmsMenuResolver $menus,
        private readonly CmsBlockRendererRegistry $blocks,
        private readonly CmsDownloadProvider $downloads,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_cms_menu', [$this->menus, 'resolve']),
            new TwigFunction('cardnext_cms_block_template', [$this->blocks, 'template']),
            new TwigFunction('cardnext_cms_download_center', [$this->downloads, 'downloadCenter']),
            new TwigFunction('cardnext_cms_product_downloads', [$this->downloads, 'forProduct']),
        ];
    }
}
