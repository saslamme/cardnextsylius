<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\InternalOrderNotificationListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

final class InternalOrderNotificationListenerTest extends TestCase
{
    public function testRenderingFailureCannotBreakCompletedCheckout(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('CN-123');

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willThrowException(new \RuntimeException('Template failure'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Cardnext internal order notification could not be sent.',
            self::callback(static fn (array $context): bool =>
                'CN-123' === $context['order_number'] && $context['exception'] instanceof \RuntimeException),
        );

        $listener = new InternalOrderNotificationListener(
            $this->createMock(MailerInterface::class),
            $twig,
            $logger,
        );

        $listener(new GenericEvent($order));
        self::addToAssertionCount(1);
    }

    public function testMailFailureCannotBreakCompletedCheckout(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('CN-456');

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<p>Order</p>');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('Mail failure'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Cardnext internal order notification could not be sent.',
            self::callback(static fn (array $context): bool =>
                'CN-456' === $context['order_number'] && $context['exception'] instanceof \RuntimeException),
        );

        $listener = new InternalOrderNotificationListener($mailer, $twig, $logger);
        $listener(new GenericEvent($order));
        self::addToAssertionCount(1);
    }
}
