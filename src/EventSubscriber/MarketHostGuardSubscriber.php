<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\International\CardnextMarketRegistry;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MarketHostGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(private CardnextMarketRegistry $markets, private ChannelContextInterface $channels)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['verifyResolvedChannel', -20]];
    }

    public function verifyResolvedChannel(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $hostMarket = $this->markets->forHostname($event->getRequest()->getHost());
        if ($hostMarket === null) {
            return; // Keep local, preview and test hosts compatible.
        }
        if ($this->channels->getChannel()->getCode() !== $hostMarket->channelCode) {
            throw new NotFoundHttpException('The requested market host does not match the resolved channel.');
        }
    }
}
