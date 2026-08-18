<?php

declare(strict_types=1);

namespace App\Twig\Component;

use App\Entity\Channel\Channel;
use App\Entity\Configurator\Configurator;
use App\Entity\Taxonomy\Taxon;
use App\Repository\Configurator\ConfiguratorRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent(name: 'cardnext:taxon:configurators', template: 'shop/category/configurator_list.html.twig')]
final class TaxonConfiguratorsComponent
{
    public Taxon $taxon;

    /** @var list<Configurator> */
    #[ExposeInTemplate]
    public array $configurators = [];

    public function __construct(private readonly ConfiguratorRepository $repository, private readonly ChannelContextInterface $channelContext, private readonly LocaleContextInterface $localeContext)
    {
    }

    #[PostMount]
    public function load(): void
    {
        $channel = $this->channelContext->getChannel();
        if ($channel instanceof Channel) {
            $this->configurators = $this->repository->findPublicByTaxon($this->taxon, $this->localeContext->getLocaleCode(), $channel);
        }
    }
}
