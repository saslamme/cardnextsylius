<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Seo\SeoLandingPage;
use App\Form\Type\SeoLandingPageType;
use App\Seo\LandingPageContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
#[Route('/admin/cardnext/seo-landingpages')]
final class SeoLandingPageAdminController extends AbstractController
{
    #[Route('', name: 'cardnext_admin_seo_landing_page_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response { return $this->render('admin/cardnext/seo_landing_page/index.html.twig', ['pages' => $em->getRepository(SeoLandingPage::class)->findBy([], ['updatedAt' => 'DESC'])]); }
    #[Route('/new', name: 'cardnext_admin_seo_landing_page_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, LandingPageContentSanitizer $sanitizer, RouterInterface $router): Response { return $this->form(new SeoLandingPage(), $request, $em, $sanitizer, $router); }
    #[Route('/{id}/edit', name: 'cardnext_admin_seo_landing_page_update', methods: ['GET', 'POST'])]
    public function update(SeoLandingPage $page, Request $request, EntityManagerInterface $em, LandingPageContentSanitizer $sanitizer, RouterInterface $router): Response { return $this->form($page, $request, $em, $sanitizer, $router); }
    #[Route('/{id}/delete', name: 'cardnext_admin_seo_landing_page_delete', methods: ['POST'])]
    public function delete(SeoLandingPage $page, Request $request, EntityManagerInterface $em): Response { if (!$this->isCsrfTokenValid('delete-seo-'.$page->getId(), (string) $request->request->get('_token'))) throw $this->createAccessDeniedException(); $em->remove($page); $em->flush(); return $this->redirectToRoute('cardnext_admin_seo_landing_page_index'); }
    private function form(SeoLandingPage $page, Request $request, EntityManagerInterface $em, LandingPageContentSanitizer $sanitizer, RouterInterface $router): Response
    {
        $form = $this->createForm(SeoLandingPageType::class, $page); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $matched = $router->match($page->getPath());
            if (!in_array($matched['_route'] ?? '', ['sylius_shop_product_index', 'sylius_shop_product_show', 'cardnext_shop_configurator_page'], true)) {
                $this->addFlash('error', 'Der Pfad kollidiert mit einer vorhandenen Shop-Route.');
            } else {
                $page->setTopContent($sanitizer->sanitize($page->getTopContent())); $page->setBottomContent($sanitizer->sanitize($page->getBottomContent()));
                $em->persist($page); $em->flush(); $this->addFlash('success', 'SEO-Landingpage wurde gespeichert.'); return $this->redirectToRoute('cardnext_admin_seo_landing_page_index');
            }
        }
        return $this->render('admin/cardnext/seo_landing_page/form.html.twig', ['form' => $form, 'page' => $page]);
    }
}
