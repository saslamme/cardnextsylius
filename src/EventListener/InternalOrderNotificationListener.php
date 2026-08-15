<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Webmozart\Assert\Assert;

#[AsEventListener(event: 'sylius.order.post_complete', priority: -100)]
final class InternalOrderNotificationListener
{
    private const RECIPIENT = 'info@cardnext.de';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $customerEmail = $order->getCustomer()?->getEmail();

        $email = (new Email())
            ->from(new Address(self::RECIPIENT, 'Cardnext Shop'))
            ->to(self::RECIPIENT)
            ->subject(sprintf(
                'Neue Cardnext-Bestellung %s',
                $order->getNumber() !== null ? '#'.$order->getNumber() : '',
            ))
            ->html($this->twig->render('email/internal_order_notification.html.twig', [
                'order' => $order,
            ]));

        if (is_string($customerEmail) && $customerEmail !== '') {
            $email->replyTo($customerEmail);
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Cardnext internal order notification could not be sent.', [
                'order_number' => $order->getNumber(),
                'exception' => $exception,
            ]);
        }
    }
}
