<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer\Customer;
use App\Entity\User\ShopUser;
use App\Enum\Maintenance\MaintenanceContractStatus;
use App\Repository\Maintenance\MaintenanceContractRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account/wartungsvertraege', priority: 200)]
final class MaintenanceContractAccountController extends AbstractController
{
    #[Route('', name: 'cardnext_shop_account_maintenance_contract_index', methods: ['GET'])]
    public function index(MaintenanceContractRepository $repository, ClockInterface $clock): Response
    {
        $user = $this->getUser();
        if (!$user instanceof ShopUser || !$user->getCustomer() instanceof Customer) {
            throw $this->createAccessDeniedException();
        }
        $today = \DateTimeImmutable::createFromInterface($clock->now())->setTime(0, 0);
        $contracts = $repository->findForCustomer($user->getCustomer());
        $rank = [MaintenanceContractStatus::Active->value => 0, MaintenanceContractStatus::Upcoming->value => 1, MaintenanceContractStatus::Expired->value => 2];
        usort($contracts, static fn ($a, $b): int => $rank[$a->statusAt($today)->value] <=> $rank[$b->statusAt($today)->value] ?: $b->getEndsAt() <=> $a->getEndsAt());
        $response = $this->render('shop/account/maintenance_contract/index.html.twig', ['contracts' => $contracts, 'today' => $today]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
