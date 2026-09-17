<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\Product\Product;
use App\Entity\Sales\ProductExpert;
use App\Entity\Sales\ProductExpertProduct;
use App\Entity\User\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/vertrieb')]
final class ProductExpertAssortmentController extends AbstractController
{
    #[Route('/sortiment/{id?}', name: 'cardnext_admin_product_expert_assortment', methods: ['GET'])]
    public function assortment(?ProductExpert $expert, EntityManagerInterface $em): Response
    { $expert = $this->resolve($expert, $em); return $this->render('admin/product_expert/assortment.html.twig', ['expert' => $expert]); }

    #[Route('/products/search', name: 'cardnext_admin_product_expert_product_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $requested = $request->query->getInt('expert');
        $expert = $this->resolve($requested > 0 ? $em->find(ProductExpert::class, $requested) : null, $em); $q = mb_strtolower(trim($request->query->getString('q')));
        if (mb_strlen($q) < 2) return $this->json([]);
        $products = $em->createQueryBuilder()->select('DISTINCT p')->from(Product::class, 'p')->leftJoin('p.translations', 't')->leftJoin('p.variants', 'v')->leftJoin('p.manufacturer', 'm')->join('p.channels', 'c')->andWhere('p.enabled = true')->andWhere('c = :channel')->andWhere('LOWER(t.name) LIKE :q OR LOWER(p.code) LIKE :q OR LOWER(v.code) LIKE :q OR LOWER(m.name) LIKE :q')->setParameters(['channel' => $expert->getChannel(), 'q' => '%'.$q.'%'])->setMaxResults(20)->getQuery()->getResult();
        return $this->json(array_map(static fn(Product $p) => ['id' => $p->getId(), 'name' => $p->getName(), 'code' => $p->getCode(), 'manufacturer' => $p->getManufacturer()?->getName()], $products));
    }
    #[Route('/sortiment/{id}/add', name: 'cardnext_admin_product_expert_assortment_add', methods: ['POST'])]
    public function add(ProductExpert $expert, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $expert = $this->resolve($expert, $em); $this->csrf($request); $product = $em->find(Product::class, $request->request->getInt('product'));
        if (!$product || !$product->isEnabled() || !$product->hasChannel($expert->getChannel())) throw $this->createNotFoundException();
        if ($em->getRepository(ProductExpertProduct::class)->findOneBy(['expert' => $expert, 'product' => $product])) return $this->json(['added' => false], 409);
        $item = new ProductExpertProduct(); $item->setExpert($expert); $item->setProduct($product); $item->setPosition($expert->getProducts()->count()); $em->persist($item); $em->flush(); return $this->json(['added' => true, 'id' => $item->getId()]);
    }
    #[Route('/sortiment/{id}/remove/{item}', name: 'cardnext_admin_product_expert_assortment_remove', methods: ['POST'])]
    public function remove(ProductExpert $expert, ProductExpertProduct $item, Request $request, EntityManagerInterface $em): JsonResponse
    { $expert = $this->resolve($expert, $em); $this->csrf($request); if ($item->getExpert() !== $expert) throw $this->createAccessDeniedException(); $em->remove($item); $em->flush(); return $this->json(['removed' => true]); }
    #[Route('/sortiment/{id}/sort', name: 'cardnext_admin_product_expert_assortment_sort', methods: ['POST'])]
    public function sort(ProductExpert $expert, Request $request, EntityManagerInterface $em): JsonResponse
    { $expert = $this->resolve($expert, $em); $this->csrf($request); $ids = $request->toArray()['items'] ?? []; foreach ($ids as $position => $id) { $item = $em->find(ProductExpertProduct::class, (int)$id); if (!$item || $item->getExpert() !== $expert) throw $this->createAccessDeniedException(); $item->setPosition($position); } $em->flush(); return $this->json(['saved' => true]); }
    private function resolve(?ProductExpert $requested, EntityManagerInterface $em): ProductExpert
    { if ($this->isGranted('ROLE_ADMINISTRATION_ACCESS')) { if ($requested) return $requested; throw $this->createNotFoundException('Bitte einen Experten auswählen.'); } $this->denyAccessUnlessGranted('ROLE_PRODUCT_EXPERT'); $user = $this->getUser(); if (!$user instanceof AdminUser) throw $this->createAccessDeniedException(); $own = $em->getRepository(ProductExpert::class)->findOneBy(['adminUser' => $user]); if (!$own) throw $this->createAccessDeniedException('Kein Produktexperten-Profil zugeordnet.'); if ($requested && $requested !== $own) throw $this->createAccessDeniedException(); return $own; }
    private function csrf(Request $request): void { if (!$this->isCsrfTokenValid('product_expert_assortment', $request->headers->get('X-CSRF-TOKEN') ?? $request->request->getString('_token'))) throw $this->createAccessDeniedException('Ungültiges CSRF-Token.'); }
}
