<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\Manufacturer;
use App\Form\Type\ManufacturerType;
use App\Service\CardnextMediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ManufacturerAdminController extends AbstractController
{
    #[Route('/admin/cardnext/manufacturers', name: 'cardnext_admin_manufacturer_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $manufacturers = $entityManager
            ->getRepository(Manufacturer::class)
            ->findBy([], ['position' => 'ASC', 'name' => 'ASC']);

        return $this->render('admin/cardnext/manufacturer/index.html.twig', [
            'manufacturers' => $manufacturers,
        ]);
    }

    #[Route('/admin/cardnext/manufacturers/new', name: 'cardnext_admin_manufacturer_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextMediaStorage $storage,
    ): Response {
        $manufacturer = new Manufacturer();
        $form = $this->createForm(ManufacturerType::class, $manufacturer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logo */
            $logo = $form->get('logo')->getData();
            if ($logo !== null) {
                $storage->uploadManufacturerLogo($manufacturer, $logo);
            }

            $entityManager->persist($manufacturer);
            $entityManager->flush();

            $this->addFlash('success', 'Hersteller wurde angelegt.');

            return $this->redirectToRoute('cardnext_admin_manufacturer_index');
        }

        return $this->render('admin/cardnext/manufacturer/form.html.twig', [
            'form' => $form,
            'manufacturer' => $manufacturer,
            'page_title' => 'Hersteller anlegen',
        ]);
    }

    #[Route('/admin/cardnext/manufacturers/{id}/edit', name: 'cardnext_admin_manufacturer_update', methods: ['GET', 'POST'])]
    public function update(
        Manufacturer $manufacturer,
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextMediaStorage $storage,
    ): Response {
        $form = $this->createForm(ManufacturerType::class, $manufacturer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logo */
            $logo = $form->get('logo')->getData();
            if ($logo !== null) {
                $storage->uploadManufacturerLogo($manufacturer, $logo);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Hersteller wurde aktualisiert.');

            return $this->redirectToRoute('cardnext_admin_manufacturer_index');
        }

        return $this->render('admin/cardnext/manufacturer/form.html.twig', [
            'form' => $form,
            'manufacturer' => $manufacturer,
            'page_title' => 'Hersteller bearbeiten',
        ]);
    }

    #[Route('/admin/cardnext/manufacturers/{id}/delete', name: 'cardnext_admin_manufacturer_delete', methods: ['POST'])]
    public function delete(
        Manufacturer $manufacturer,
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextMediaStorage $storage,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-manufacturer-' . $manufacturer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $storage->removeManufacturerLogo($manufacturer);
        $entityManager->remove($manufacturer);
        $entityManager->flush();

        $this->addFlash('success', 'Hersteller wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_manufacturer_index');
    }
}
