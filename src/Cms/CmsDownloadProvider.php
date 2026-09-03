<?php
declare(strict_types=1);
namespace App\Cms;
use App\Repository\Cms\CmsDownloadRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;
final class CmsDownloadProvider { public function __construct(private CmsDownloadRepository $repository,private ChannelContextInterface $channels,private LocaleContextInterface $locales,private RequestStack $requests){} public function visible(array $config=[]):array{$query=$this->requests->getCurrentRequest()?->query; $filters=['q'=>$query?->getString('q'),'type'=>$query?->getString('type')?:($config['types'][0]??null),'manufacturer'=>$query?->getString('manufacturer')?:($config['manufacturer']??null),'os'=>$query?->getString('os')];return $this->repository->findVisible($this->channels->getChannel(),$this->locales->getLocaleCode(),$filters,isset($config['limit'])?(int)$config['limit']:null);}}
