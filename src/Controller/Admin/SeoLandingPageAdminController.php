<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Channel\Channel;
use App\Entity\Seo\SeoLandingPage;
use App\Entity\Taxonomy\Taxon;
use App\Form\Type\SeoLandingPageType;
use App\Repository\Seo\SeoLandingPageRepository;
use App\Seo\LandingPageContentSanitizer;
use App\Seo\LandingPagePath;
use App\Seo\LandingPageRouteValidator;
use App\Service\ProductFacetDefinitionService;
use App\Service\ProductFacetService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
#[Route('/admin/cardnext/seo-landingpages')]
final class SeoLandingPageAdminController extends AbstractController
{
    #[Route('', name: 'cardnext_admin_seo_landing_page_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response { return $this->render('admin/cardnext/seo_landing_page/index.html.twig', ['pages' => $em->getRepository(SeoLandingPage::class)->findBy([], ['updatedAt' => 'DESC'])]); }
    #[Route('/new', name: 'cardnext_admin_seo_landing_page_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, LandingPageContentSanitizer $sanitizer, LandingPageRouteValidator $routeValidator): Response { return $this->form(new SeoLandingPage(), $request, $em, $sanitizer, $routeValidator); }
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
    #[Route('/seo-check', name: 'cardnext_admin_seo_landing_page_check', methods: ['GET'])]
    public function seoCheck(Request $request, EntityManagerInterface $em, SeoLandingPageRepository $pages, LandingPageRouteValidator $routeValidator): JsonResponse
    {
        $channel = $em->find(Channel::class, $request->query->getInt('channel'));
        $locale = (string) $request->query->get('locale');
        if (!$channel instanceof Channel || !$channel->getLocales()->exists(fn (int $key, $item): bool => $item->getCode() === $locale)) {
            return $this->json(['error' => 'Ungültige Kombination aus Verkaufskanal und Sprache.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $path = LandingPagePath::normalize((string) $request->query->get('path'));
        } catch (\InvalidArgumentException) {
            return $this->json(['pathValid' => false, 'pathUnique' => false, 'metaTitleUnique' => true, 'h1Unique' => true]);
        }
        $duplicates = $pages->findDuplicateFields($channel, $locale, $path, (string) $request->query->get('metaTitle'), (string) $request->query->get('h1'), $request->query->getInt('id') ?: null);
        return $this->json(['pathValid' => $routeValidator->isAllowed($path), 'pathUnique' => !$duplicates['path'], 'metaTitleUnique' => !$duplicates['metaTitle'], 'h1Unique' => !$duplicates['h1']]);
    }
    #[Route('/{id}/edit', name: 'cardnext_admin_seo_landing_page_update', methods: ['GET', 'POST'])]
    public function update(SeoLandingPage $page, Request $request, EntityManagerInterface $em, LandingPageContentSanitizer $sanitizer, LandingPageRouteValidator $routeValidator): Response { return $this->form($page, $request, $em, $sanitizer, $routeValidator); }
    #[Route('/{id}/delete', name: 'cardnext_admin_seo_landing_page_delete', methods: ['POST'])]
    public function delete(SeoLandingPage $page, Request $request, EntityManagerInterface $em): Response { if (!$this->isCsrfTokenValid('delete-seo-'.$page->getId(), (string) $request->request->get('_token'))) throw $this->createAccessDeniedException(); $em->remove($page); $em->flush(); return $this->redirectToRoute('cardnext_admin_seo_landing_page_index'); }
    private function form(SeoLandingPage $page, Request $request, EntityManagerInterface $em, LandingPageContentSanitizer $sanitizer, LandingPageRouteValidator $routeValidator): Response
    {
        $form = $this->createForm(SeoLandingPageType::class, $page); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$routeValidator->isAllowed($page->getPath())) {
                $this->addFlash('error', 'Der gewählte URL-Pfad kann nicht als SEO-Landingpage verwendet werden.');
            } else {
                $page->setTopContent($sanitizer->sanitize($page->getTopContent())); $page->setBottomContent($sanitizer->sanitize($page->getBottomContent()));
                $em->persist($page); $em->flush(); $this->addFlash('success', 'SEO-Landingpage wurde gespeichert.'); return $this->redirectToRoute('cardnext_admin_seo_landing_page_index');
            }
        }
        return $this->render('admin/cardnext/seo_landing_page/form.html.twig', ['form' => $form, 'page' => $page]);
    }
}
