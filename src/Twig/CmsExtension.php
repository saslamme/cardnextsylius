<?php

declare(strict_types=1);

namespace App\Twig;

use App\Cms\CmsBlockRendererRegistry;
use App\Cms\CmsDownloadProvider;
use App\Cms\CmsMenuResolver;
use App\Cms\VideoEmbed;
use App\Cms\VideoEmbedResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CmsExtension extends AbstractExtension
{
    public function __construct(
        private readonly CmsMenuResolver $menus,
        private readonly CmsBlockRendererRegistry $blocks,
        private readonly CmsDownloadProvider $downloads,
        private readonly VideoEmbedResolver $videoEmbedResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_cms_menu', [$this->menus, 'resolve']),
            new TwigFunction('cardnext_cms_block_template', [$this->blocks, 'template']),
            new TwigFunction('cardnext_cms_download_center', [$this->downloads, 'downloadCenter']),
            new TwigFunction('cardnext_cms_product_downloads', [$this->downloads, 'forProduct']),
            new TwigFunction('cardnext_cms_video_embed', $this->videoEmbed(...)),
        ];
    }

    public function videoEmbed(mixed $provider, mixed $url, mixed $privacyMode = true, mixed $showControls = true): ?VideoEmbed
    {
        if (!is_string($provider) || !is_string($url)) {
            return null;
        }

        return $this->videoEmbedResolver->resolve(
            $provider,
            $url,
            is_bool($privacyMode) ? $privacyMode : true,
            is_bool($showControls) ? $showControls : true,
        );
    }
}
