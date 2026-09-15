<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Channel\Channel;
use App\Entity\Seo\SeoLandingPage;
use App\Entity\Taxonomy\Taxon;
use App\Form\Type\SeoLandingPageType;
use App\Seo\LandingPageContentSanitizer;
use App\Service\ProductFacetDefinitionService;
use App\Service\ProductFacetService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    #[Route('/filters', name: 'cardnext_admin_seo_landing_page_filters', methods: ['GET'])]
    public function filters(Request $request, EntityManagerInterface $em, ProductFacetDefinitionService $definitions, ProductFacetService $facets): JsonResponse
    {
        $taxon = $em->find(Taxon::class, $request->query->getInt('taxon'));
        $channel = $em->find(Channel::class, $request->query->getInt('channel'));
        if (!$taxon instanceof Taxon || !$channel instanceof Channel || null === $profile = $definitions->profileForTaxon($taxon)) {
            return $this->json(['manufacturers' => [], 'facets' => []]);
        }

        $locale = (string) $request->query->get('locale', 'de_DE');
        $request->setLocale($locale);
        $available = $facets->getFacets($taxon, $channel, $request, $profile);
        $result = [];
        foreach ($definitions->forProfile($profile, $locale) as $definition) {
            $choices = [];
            foreach ($definition['choices'] as $label => $value) {
                if (isset($available['attributes'][$definition['attribute']][(string) $value])) {
                    $choices[] = ['value' => $value, 'label' => $label];
                }
            }
            if ($choices !== []) {
                $result[] = ['attribute' => $definition['attribute'], 'label' => $definition['label'], 'type' => $definition['type'], 'choices' => $choices];
            }
        }
        $manufacturers = [];
        foreach ($available['manufacturer'] as $code => $manufacturer) {
            $manufacturers[] = ['value' => $code, 'label' => $manufacturer['label']];
        }

        return $this->json(['manufacturers' => $manufacturers, 'facets' => $result]);
    }
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
