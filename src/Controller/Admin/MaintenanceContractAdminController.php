<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Maintenance\MaintenanceContract;
use App\Service\Maintenance\MaintenanceContractSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
#[Route('/admin/cardnext/maintenance-contracts')]
final class MaintenanceContractAdminController extends AbstractController
{
    #[Route('', name: 'cardnext_admin_maintenance_contract_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, ClockInterface $clock): Response
    {
        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        $qb = $em->createQueryBuilder()->select('contract', 'customer', 'profile')->from(MaintenanceContract::class, 'contract')->join('contract.customer', 'customer')->leftJoin('customer.b2bProfile', 'profile');
        if ($query !== '') {
            $qb->andWhere('LOWER(contract.serialNumber) LIKE :q OR LOWER(contract.erpCustomerNumber) LIKE :q OR LOWER(contract.contractReference) LIKE :q OR LOWER(customer.email) LIKE :q OR LOWER(profile.companyName) LIKE :q')->setParameter('q', '%' . $query . '%');
        }
        /** @var list<MaintenanceContract> $contracts */
        $contracts = $qb->orderBy('contract.endsAt', 'DESC')->getQuery()->getResult();

        return $this->render('admin/cardnext/maintenance_contract/index.html.twig', ['contracts' => $contracts, 'today' => \DateTimeImmutable::createFromInterface($clock->now())->setTime(0, 0), 'query' => $query]);
    }

    #[Route('/sync', name: 'cardnext_admin_maintenance_contract_sync', methods: ['POST'])]
    public function sync(Request $request, MaintenanceContractSyncService $service): Response
    {
        if (!$this->isCsrfTokenValid('maintenance-contract-sync', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $r = $service->synchronize();
            $this->addFlash('success', sprintf('%d gelesen, %d neu, %d aktualisiert, %d unverändert, %d übersprungen, %d fehlerhaft.', $r->fetched, $r->created, $r->updated, $r->unchanged, $r->skipped, $r->errors));
        } catch (\Throwable) {
            $this->addFlash('error', 'ERP-Synchronisierung fehlgeschlagen. Vorhandene lokale Daten bleiben erhalten.');
        }

        return $this->redirectToRoute('cardnext_admin_maintenance_contract_index');
    }

    #[Route('/{id}/note', name: 'cardnext_admin_maintenance_contract_note', methods: ['POST'])]
    public function note(MaintenanceContract $contract, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('maintenance-contract-note-' . $contract->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $contract->setInternalNote((string) $request->request->get('internal_note'));
        $em->flush();
        $this->addFlash('success', 'Interne Notiz gespeichert.');

        return $this->redirectToRoute('cardnext_admin_maintenance_contract_index');
    }
}
