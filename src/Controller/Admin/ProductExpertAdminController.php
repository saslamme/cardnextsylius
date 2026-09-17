<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\Sales\ProductExpert;
use App\Form\Type\ProductExpertType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/vertrieb/produktexperten', name: 'cardnext_admin_product_expert_')]
final class ProductExpertAdminController extends AbstractController
{
    private function requireAdmin(): void { $this->denyAccessUnlessGranted('ROLE_ADMINISTRATION_ACCESS'); }
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response { $this->requireAdmin(); return $this->render('admin/product_expert/index.html.twig', ['experts' => $em->getRepository(ProductExpert::class)->findBy([], ['displayName' => 'ASC'])]); }
    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response { $this->requireAdmin(); return $this->form(new ProductExpert(), $request, $em); }
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(ProductExpert $expert, Request $request, EntityManagerInterface $em): Response { $this->requireAdmin(); return $this->form($expert, $request, $em); }
    private function form(ProductExpert $expert, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProductExpertType::class, $expert); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $em->persist($expert); $em->flush(); return $this->redirectToRoute('cardnext_admin_product_expert_index'); }
        return $this->render('admin/product_expert/form.html.twig', ['form' => $form, 'expert' => $expert]);
    }
}
