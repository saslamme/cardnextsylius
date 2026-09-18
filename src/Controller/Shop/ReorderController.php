<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Channel\Channel;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\User\ShopUser;
use App\Service\Order\ReorderService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/account/bestellungen', priority: 200)]
final class ReorderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelContextInterface $channelContext,
        private readonly ReorderService $reorderService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/{number}/wiederbestellen', name: 'cardnext_shop_account_order_reorder', methods: ['POST'])]
    public function __invoke(string $number, Request $request): Response
    {
        $user = $this->getUser();
        $customer = $user instanceof ShopUser ? $user->getCustomer() : null;
        $channel = $this->channelContext->getChannel();
        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException();
        }
        if (!$channel instanceof Channel) {
            throw $this->createNotFoundException();
        }

        $order = $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->andWhere('o.number = :number')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.channel = :channel')
            ->andWhere('o.checkoutCompletedAt IS NOT NULL')
            ->setParameter('number', $number)
            ->setParameter('customer', $customer)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$order instanceof Order) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('reorder_'.$order->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $result = $this->reorderService->reorder($order, $customer, $channel);
        if ($result->addedItems === 0) {
            $this->addFlash('info', $this->translator->trans('cardnext.reorder.none'));
        } elseif ($result->skippedTotal() > 0) {
            $this->addFlash('success', $this->translator->trans('cardnext.reorder.partial', ['%added%' => $result->addedItems, '%skipped%' => $result->skippedTotal()]));
        } else {
            $this->addFlash('success', $this->translator->trans('cardnext.reorder.success'));
        }
        if ($result->skippedProtectionItems > 0) {
            $this->addFlash('info', $this->translator->trans('cardnext.reorder.protection_notice'));
        }
        if ($result->skippedConfiguredItems > 0) {
            $this->addFlash('warning', $this->translator->trans('cardnext.reorder.configured_unavailable'));
        }
        if ($result->skippedBundles > 0) {
            $this->addFlash('warning', $this->translator->trans('cardnext.reorder.bundle_unavailable'));
        }

        return $result->addedItems > 0
            ? $this->redirectToRoute('sylius_shop_cart_summary')
            : $this->redirectToRoute('sylius_shop_account_order_show', ['number' => $number]);
    }
}
