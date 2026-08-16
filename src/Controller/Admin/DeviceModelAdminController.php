<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\DeviceModel;
use App\Entity\Product\Manufacturer;
use App\Entity\Product\ProductDeviceCompatibility;
use App\Form\Type\DeviceModelType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class DeviceModelAdminController extends AbstractController
{
    #[Route('/admin/cardnext/device-models', name: 'cardnext_admin_device_model_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $query = trim((string) $request->query->get('q'));
        $manufacturerId = (int) $request->query->get('manufacturer');
        $type = trim((string) $request->query->get('device_type'));
        $status = trim((string) $request->query->get('status'));
        $qb = $entityManager->getRepository(DeviceModel::class)->createQueryBuilder('device')
            ->join('device.manufacturer', 'manufacturer')->addSelect('manufacturer')
            ->leftJoin('device.aliases', 'alias')->addSelect('alias')
            ->orderBy('manufacturer.name', 'ASC')->addOrderBy('device.name', 'ASC');
        if ($query !== '') {
            $qb->andWhere('LOWER(device.name) LIKE :query OR LOWER(device.code) LIKE :query OR LOWER(alias.alias) LIKE :query')->setParameter('query', '%' . mb_strtolower($query) . '%');
        }
        if ($manufacturerId > 0) {
            $qb->andWhere('manufacturer.id = :manufacturer')->setParameter('manufacturer', $manufacturerId);
        }
        if (isset(DeviceModel::typeLabels()[$type])) {
            $qb->andWhere('device.deviceType = :type')->setParameter('type', $type);
        }
        if (isset(DeviceModel::statusLabels()[$status])) {
            $qb->andWhere('device.status = :status')->setParameter('status', $status);
        }

        return $this->render('admin/cardnext/device_model/index.html.twig', [
            'devices' => $qb->getQuery()->getResult(),
            'manufacturers' => $entityManager->getRepository(Manufacturer::class)->findBy([], ['name' => 'ASC']),
            'type_labels' => DeviceModel::typeLabels(), 'status_labels' => DeviceModel::statusLabels(),
        ]);
    }

    #[Route('/admin/cardnext/device-models/new', name: 'cardnext_admin_device_model_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $device = new DeviceModel();

        return $this->handleForm($device, $request, $entityManager, true);
    }

    #[Route('/admin/cardnext/device-models/{id}/edit', name: 'cardnext_admin_device_model_update', methods: ['GET', 'POST'])]
    public function update(DeviceModel $device, Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->handleForm($device, $request, $entityManager, false);
    }

    #[Route('/admin/cardnext/device-models/{id}/delete', name: 'cardnext_admin_device_model_delete', methods: ['POST'])]
    public function delete(DeviceModel $device, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-device-model-' . $device->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        if ($entityManager->getRepository(ProductDeviceCompatibility::class)->count(['deviceModel' => $device]) > 0) {
            $this->addFlash('error', 'Das Gerätemodell wird von Produkten verwendet und kann nicht gelöscht werden. Setzen Sie den Status stattdessen auf „Eingestellt“.');
        } else {
            $entityManager->remove($device);
            $entityManager->flush();
            $this->addFlash('success', 'Gerätemodell wurde gelöscht.');
        }

        return $this->redirectToRoute('cardnext_admin_device_model_index');
    }

    private function handleForm(DeviceModel $device, Request $request, EntityManagerInterface $entityManager, bool $new): Response
    {
        $form = $this->createForm(DeviceModelType::class, $device);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($device);
            $entityManager->flush();
            $this->addFlash('success', $new ? 'Gerätemodell wurde angelegt.' : 'Gerätemodell wurde aktualisiert.');

            return $this->redirectToRoute('cardnext_admin_device_model_index');
        }

        return $this->render('admin/cardnext/device_model/form.html.twig', ['form' => $form, 'device' => $device, 'page_title' => $new ? 'Gerätemodell anlegen' : 'Gerätemodell bearbeiten']);
    }
}
