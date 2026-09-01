<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Channel\Channel;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerB2BProfile;
use App\Entity\Customer\CustomerGroup;
use App\Service\B2BCustomerCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class B2BCustomerAdminController extends AbstractController
{
    #[Route('/admin/cardnext/b2b-customers', name: 'cardnext_admin_b2b_customer_index', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;

        $qb = $entityManager
            ->createQueryBuilder()
            ->select('profile', 'customer', 'customerGroup')
            ->from(CustomerB2BProfile::class, 'profile')
            ->join('profile.customer', 'customer')
            ->leftJoin('customer.group', 'customerGroup')
        ;

        if ($query !== '') {
            $qb
                ->andWhere(
                    'LOWER(customer.email) LIKE :query
                    OR LOWER(profile.companyName) LIKE :query
                    OR LOWER(profile.customerNumber) LIKE :query
                    OR LOWER(profile.erpCustomerNumber) LIKE :query
                    OR LOWER(profile.vatNumber) LIKE :query',
                )
                ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ;
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(profile.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        /** @var list<CustomerB2BProfile> $profiles */
        $profiles = $qb
            ->orderBy('profile.companyName', 'ASC')
            ->addOrderBy('customer.emailCanonical', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $this->render('admin/cardnext/b2b_customer/index.html.twig', [
            'page_title' => 'B2B-Kunden',
            'profiles' => $profiles,
            'customer_groups' => $entityManager->getRepository(CustomerGroup::class)->findBy([], ['name' => 'ASC']),
            'sales_channels' => $entityManager->getRepository(Channel::class)->findBy([], ['enabled' => 'DESC', 'code' => 'ASC']),
            'query' => $query,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
            'total' => $total,
        ]);
    }

    #[Route('/admin/cardnext/b2b-customers/create', name: 'cardnext_admin_b2b_customer_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'create-b2b-customer',
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $email = mb_strtolower(trim((string) $request->request->get('email')));

        /** @var Customer|null $customer */
        $customer = $entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => $email]);

        if (!$customer instanceof Customer) {
            $customer = $entityManager->getRepository(Customer::class)->findOneBy(['email' => $email]);
        }

        if (!$customer instanceof Customer) {
            $this->addFlash('error', sprintf('Sylius-Kunde "%s" wurde nicht gefunden.', $email));

            return $this->redirectToRoute('cardnext_admin_b2b_customer_index');
        }

        if ($customer->getB2bProfile() instanceof CustomerB2BProfile) {
            $this->addFlash('error', 'Für diesen Kunden existiert bereits ein B2B-Profil.');

            return $this->redirectToRoute('cardnext_admin_b2b_customer_index', [
                'q' => $email,
            ]);
        }

        $profile = new CustomerB2BProfile();
        $profile->setCustomer($customer);

        $entityManager->persist($profile);
        $entityManager->flush();

        $this->addFlash('success', 'B2B-Profil wurde angelegt.');

        return $this->redirectToRoute('cardnext_admin_b2b_customer_index', [
            'q' => $email,
        ]);
    }

    #[Route('/admin/cardnext/b2b-customers/{id}/update', name: 'cardnext_admin_b2b_customer_update', methods: ['POST'])]
    public function update(
        CustomerB2BProfile $profile,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'update-b2b-customer-' . $profile->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $profile->setCustomerNumber((string) $request->request->get('customer_number'));
        $profile->setCompanyName((string) $request->request->get('company_name'));
        $profile->setVatNumber((string) $request->request->get('vat_number'));
        $profile->setErpCustomerNumber((string) $request->request->get('erp_customer_number'));
        $profile->setContactPerson((string) $request->request->get('contact_person'));
        $profile->setInvoiceAllowed($request->request->has('invoice_allowed'));
        $profile->setPurchaseOrderRequired($request->request->has('purchase_order_required'));
        $profile->setEnabled($request->request->has('enabled'));
        $profile->setNotes((string) $request->request->get('notes'));

        $paymentTerm = trim((string) $request->request->get('payment_term_days'));
        $profile->setPaymentTermDays($paymentTerm !== '' ? max(0, (int) $paymentTerm) : null);

        $creditLimit = trim((string) $request->request->get('credit_limit'));
        $profile->setCreditLimit($creditLimit !== '' ? $this->moneyToMinor($creditLimit) : null);

        $creditLimitCurrency = strtoupper(trim((string) $request->request->get('credit_limit_currency')));
        if ($creditLimitCurrency !== '' && !preg_match('/^[A-Z]{3}$/', $creditLimitCurrency)) {
            throw new \InvalidArgumentException('Ungültiger ISO-Währungscode für das Kreditlimit.');
        }
        $profile->setCreditLimitCurrency($creditLimitCurrency);

        $groupCode = trim((string) $request->request->get('customer_group_code'));
        if ($groupCode === '') {
            $profile->getCustomer()->setGroup(null);
        } else {
            /** @var CustomerGroup|null $group */
            $group = $entityManager->getRepository(CustomerGroup::class)->findOneBy(['code' => $groupCode]);

            if (!$group instanceof CustomerGroup) {
                throw new \InvalidArgumentException(sprintf('Kundengruppe "%s" wurde nicht gefunden.', $groupCode));
            }

            $profile->getCustomer()->setGroup($group);
        }

        $channelCode = trim((string) $request->request->get('sales_channel_code'));
        if ($channelCode === '') {
            $profile->getCustomer()->setSalesChannel(null);
        } else {
            /** @var Channel|null $salesChannel */
            $salesChannel = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $channelCode]);
            if (!$salesChannel instanceof Channel) {
                throw new \InvalidArgumentException(sprintf('Verkaufskanal "%s" wurde nicht gefunden.', $channelCode));
            }
            $profile->getCustomer()->setSalesChannel($salesChannel);
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'B2B-Daten für %s wurden gespeichert.',
            (string) $profile->getCustomer()->getEmail(),
        ));

        return $this->redirectToRoute('cardnext_admin_b2b_customer_index', [
            'q' => (string) $profile->getCustomer()->getEmail(),
        ]);
    }

    #[Route('/admin/cardnext/b2b-customers/{id}/delete', name: 'cardnext_admin_b2b_customer_delete', methods: ['POST'])]
    public function delete(
        CustomerB2BProfile $profile,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete-b2b-customer-' . $profile->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $customer = $profile->getCustomer();
        $customer->setB2bProfile(null);

        $entityManager->remove($profile);
        $entityManager->flush();

        $this->addFlash('success', 'B2B-Profil wurde entfernt. Der Sylius-Kunde bleibt erhalten.');

        return $this->redirectToRoute('cardnext_admin_b2b_customer_index');
    }

    #[Route('/admin/cardnext/b2b-customers/import', name: 'cardnext_admin_b2b_customer_import', methods: ['POST'])]
    public function import(
        Request $request,
        B2BCustomerCsvImporter $importer,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'import-b2b-customers',
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $file = $request->files->get('csv_file');

        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Bitte eine CSV-Datei auswählen.');

            return $this->redirectToRoute('cardnext_admin_b2b_customer_index');
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            $this->addFlash('error', 'Es werden nur CSV-Dateien akzeptiert.');

            return $this->redirectToRoute('cardnext_admin_b2b_customer_index');
        }

        $dryRun = $request->request->has('dry_run');
        $result = $importer->import($file->getPathname(), $dryRun);

        $this->addFlash(
            'success',
            sprintf(
                '%s: %d Zeilen, %d neu, %d aktualisiert, %d unverändert.',
                $dryRun ? 'Dry-Run erfolgreich' : 'Import abgeschlossen',
                $result['rows'],
                $result['created'],
                $result['updated'],
                $result['unchanged'],
            ),
        );

        foreach ($result['warnings'] as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $this->redirectToRoute('cardnext_admin_b2b_customer_index');
    }

    private function moneyToMinor(string $value): int
    {
        $value = trim(str_replace(['€', ' '], '', $value));

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Kreditlimit hat ein ungültiges Preisformat.');
        }

        return (int) round(((float) $value) * 100);
    }
}
