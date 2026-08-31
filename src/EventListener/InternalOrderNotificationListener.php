<?php

declare(strict_types=1);

namespace App\EventListener;

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
            $customerEmail = $order->getCustomer()?->getEmail();

            $email = (new Email())
                ->from(new Address(self::RECIPIENT, 'Cardnext Shop'))
                ->to(self::RECIPIENT)
                ->subject(sprintf(
                    'Neue Cardnext-Bestellung %s',
                    $orderNumber !== null ? '#' . $orderNumber : '',
                ))
                ->html($this->twig->render('email/internal_order_notification.html.twig', [
                    'order' => $order,
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
