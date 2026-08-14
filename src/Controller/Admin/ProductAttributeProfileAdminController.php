<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\Product;
use App\Service\ProductAttributeProfileService;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ProductAttributeProfileAdminController extends AbstractController
{
    #[Route(
        '/admin/cardnext/products/{id}/apply-attribute-profile',
        name: 'cardnext_admin_product_attribute_profile_apply',
        methods: ['POST'],
    )]
    public function apply(
        Product $product,
        Request $request,
        ProductAttributeProfileService $profiles,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'apply-product-attribute-profile-' . $product->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $result = $profiles->applyToProduct($product);

        if ($result['profile'] === null) {
            $this->addFlash(
                'warning',
                'Für den Haupt-Taxon dieses Produktes ist noch kein Cardnext-Datenprofil definiert.',
            );
        } elseif ($result['added'] === 0) {
            $this->addFlash(
                'success',
                sprintf(
                    'Das Datenprofil „%s“ ist bereits vollständig zugeordnet.',
                    $profiles->getProfileLabel($result['profile']) ?? $result['profile'],
                ),
            );
        } else {
            $this->addFlash(
                'success',
                sprintf(
                    'Datenprofil „%s“ angewendet: %d technische Felder ergänzt.',
                    $profiles->getProfileLabel($result['profile']) ?? $result['profile'],
                    $result['added'],
                ),
            );
        }

        return $this->redirectToRoute('sylius_admin_product_update', ['id' => $product->getId()]);
    }
}
