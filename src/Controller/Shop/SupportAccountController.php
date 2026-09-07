<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer\Customer;
use App\Entity\Maintenance\MaintenanceContract;
use App\Entity\Order\Order;
use App\Entity\Product\Product;
use App\Entity\Support\SupportCase;
use App\Entity\User\ShopUser;
use App\Form\Type\SupportCaseCreateType;
use App\Integration\Freshdesk\Exception\FreshdeskApiException;
use App\Repository\Maintenance\MaintenanceContractRepository;
use App\Repository\Support\SupportCaseRepository;
use App\Service\Support\FreshdeskTicketStatusPresenter;
use App\Service\Support\SupportCaseAccessDeniedException;
use App\Service\Support\SupportCaseCreateData;
use App\Service\Support\SupportCaseCreator;
use App\Service\Support\SupportCaseFreshdeskReader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account/service', priority: 200)]
final class SupportAccountController extends AbstractController
{
    public function __construct(
        private readonly SupportCaseRepository $supportCases,
        private readonly MaintenanceContractRepository $maintenanceContracts,
        private readonly ChannelContextInterface $channelContext,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'cardnext_shop_account_support_index', methods: ['GET'])]
    public function index(): Response
    {
        $cases = $this->supportCases->findForCustomerAndChannel($this->customer(), $this->channelCode());

        return $this->secure($this->render('shop/account/support/index.html.twig', ['cases' => $cases]));
    }

    #[Route('/new', name: 'cardnext_shop_account_support_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SupportCaseCreator $creator, LoggerInterface $logger): Response
    {
        $customer = $this->customer();
        $orders = $this->customerOrders($customer);
        $contracts = $this->maintenanceContracts->findForCustomer($customer);
        $products = $this->publicProducts();
        $initial = $request->isMethod('GET') ? $this->initialData($request, $orders, $contracts, $products) : [];
        $form = $this->createForm(SupportCaseCreateType::class, $initial, [
            'orders' => $orders,
            'maintenance_contracts' => $contracts,
            'products' => $products,
        ]);
        $form->handleRequest($request);

        $status = Response::HTTP_OK;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $values */
            $values = $form->getData();
            try {
                $case = $creator->create($customer, new SupportCaseCreateData(
                    $values['type'],
                    trim((string) $values['subject']),
                    trim((string) $values['description']),
                    $values['order'],
                    $values['product'],
                    $values['maintenanceContract'],
                    $values['serialNumber'],
                ));
                $this->addFlash('success', 'cardnext.support.flash.created');

                return $this->redirectToRoute('cardnext_shop_account_support_show', ['id' => $case->getId()]);
            } catch (SupportCaseAccessDeniedException) {
                throw $this->createNotFoundException();
            } catch (FreshdeskApiException $exception) {
                $logger->error('Customer support ticket creation failed.', ['operation' => $exception->operation, 'status' => $exception->httpStatus]);
                $status = Response::HTTP_SERVICE_UNAVAILABLE;
            }
        }

        return $this->secure($this->render('shop/account/support/new.html.twig', [
            'form' => $form,
            'service_unavailable' => $status === Response::HTTP_SERVICE_UNAVAILABLE,
        ], new Response(status: $status)));
    }

    #[Route('/{id}', name: 'cardnext_shop_account_support_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, SupportCaseFreshdeskReader $reader, FreshdeskTicketStatusPresenter $statusPresenter, LoggerInterface $logger): Response
    {
        $customer = $this->customer();
        $case = $this->supportCases->findOneForCustomerAndChannel($id, $customer, $this->channelCode());
        if (!$case instanceof SupportCase) {
            throw $this->createNotFoundException();
        }

        try {
            $ticket = $reader->getTicketForCustomer($case, $customer);
            $conversations = $reader->getConversationsForCustomer($case, $customer);
        } catch (SupportCaseAccessDeniedException) {
            throw $this->createNotFoundException();
        } catch (FreshdeskApiException $exception) {
            $logger->error('Customer support ticket could not be read.', ['support_case_id' => $case->getId(), 'operation' => $exception->operation, 'status' => $exception->httpStatus]);

            return $this->secure($this->render('shop/account/support/unavailable.html.twig', ['case' => $case], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE)));
        }

        return $this->secure($this->render('shop/account/support/show.html.twig', [
            'case' => $case,
            'ticket' => $ticket,
            'conversations' => $conversations,
            'status_key' => $statusPresenter->translationKey($ticket->status),
        ]));
    }

    private function customer(): Customer
    {
        $user = $this->getUser();
        if (!$user instanceof ShopUser || !$user->getCustomer() instanceof Customer) {
            throw $this->createAccessDeniedException();
        }

        return $user->getCustomer();
    }

    private function channelCode(): string
    {
        return (string) $this->channelContext->getChannel()->getCode();
    }

    /** @return list<Order> */
    private function customerOrders(Customer $customer): array
    {
        /** @var list<Order> $orders */
        $orders = $this->entityManager->getRepository(Order::class)->createQueryBuilder('customerOrder')
            ->andWhere('customerOrder.customer = :customer')
            ->andWhere('customerOrder.channel = :channel')
            ->andWhere('customerOrder.number IS NOT NULL')
            ->setParameter('customer', $customer)
            ->setParameter('channel', $this->channelContext->getChannel())
            ->orderBy('customerOrder.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()->getResult();

        return $orders;
    }

    /** @return list<Product> */
    private function publicProducts(): array
    {
        /** @var list<Product> $products */
        $products = $this->entityManager->getRepository(Product::class)->createQueryBuilder('product')
            ->innerJoin('product.channels', 'channel')
            ->andWhere('product.enabled = true')
            ->andWhere('product.addonOnly = false')
            ->andWhere('channel = :channel')
            ->setParameter('channel', $this->channelContext->getChannel())
            ->orderBy('product.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()->getResult();

        return $products;
    }

    /** @param list<Order> $orders @param list<MaintenanceContract> $contracts @param list<Product> $products */
    private function initialData(Request $request, array $orders, array $contracts, array $products): array
    {
        $find = static function (array $entities, mixed $id): mixed {
            if (!is_scalar($id) || !ctype_digit((string) $id)) {
                return null;
            }
            foreach ($entities as $entity) {
                if ($entity->getId() === (int) $id) {
                    return $entity;
                }
            }

            return null;
        };

        return [
            'order' => $find($orders, $request->query->get('order')),
            'maintenanceContract' => $find($contracts, $request->query->get('maintenanceContract')),
            'product' => $find($products, $request->query->get('product')),
            'serialNumber' => mb_substr(trim((string) $request->query->get('serialNumber')), 0, 255),
        ];
    }

    private function secure(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
