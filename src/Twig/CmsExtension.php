<?php
declare(strict_types=1);
namespace App\Twig;
use App\Cms\{CmsBlockRendererRegistry,CmsDownloadProvider,CmsMenuResolver}; use Twig\Extension\AbstractExtension; use Twig\TwigFunction;
final class CmsExtension extends AbstractExtension { public function __construct(private readonly CmsMenuResolver $menus,private readonly CmsBlockRendererRegistry $blocks,private readonly CmsDownloadProvider $downloads){} public function getFunctions():array{return[new TwigFunction('cardnext_cms_menu',[$this->menus,'resolve']),new TwigFunction('cardnext_cms_block_template',[$this->blocks,'template']),new TwigFunction('cardnext_cms_downloads',[$this->downloads,'visible'])];} }
