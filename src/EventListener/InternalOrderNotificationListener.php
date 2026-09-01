<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Email\ChannelEmailBranding;
use App\Email\ChannelEmailBrandingResolver;
use App\Entity\Channel\Channel;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsEventListener(event: 'sylius.order.post_complete', priority: -100)]
final class InternalOrderNotificationListener
{
    private const RECIPIENT = 'info@cardnext.de';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly ?ChannelEmailBrandingResolver $brandingResolver = null,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            $this->logger->error('Cardnext internal order notification received an invalid event subject.', [
                'order_number' => null,
                'subject_type' => get_debug_type($order),
            ]);

            return;
        }

        $orderNumber = $order->getNumber();

        try {
            $channel = $order->getChannel();
            $branding = $channel instanceof Channel && $this->brandingResolver !== null
                ? $this->brandingResolver->resolve($channel)
                : new ChannelEmailBranding('Cardnext', 'cardnext/cardnext.svg', '/cardnext/cardnext.svg', 'Cardnext Shop', self::RECIPIENT, null);
            $customerEmail = $order->getCustomer()?->getEmail();

            $email = (new Email())
                ->from(new Address($branding->senderAddress, $branding->senderName))
                ->to(self::RECIPIENT)
                ->subject(sprintf(
                    'Neue %s-Bestellung %s',
                    $branding->brandName,
                    $orderNumber !== null ? '#' . $orderNumber : '',
                ))
                ->html($this->twig->render('email/internal_order_notification.html.twig', [
                    'order' => $order,
                    'emailBranding' => $branding,
                ]));

            if (is_string($customerEmail) && $customerEmail !== '') {
                $email->replyTo($customerEmail);
            }

            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            $this->logger->error('Cardnext internal order notification could not be sent.', [
                'order_number' => $orderNumber,
                'exception' => $exception,
            ]);
        }
    }
}
