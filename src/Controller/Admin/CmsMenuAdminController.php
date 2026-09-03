<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Cms\CmsMenu;
use App\Entity\Cms\CmsMenuItem;
use App\Entity\Locale\Locale;
use App\Form\Cms\CmsMenuItemType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class CmsMenuAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly RouterInterface $router)
    {
    }

    #[Route('/admin/cardnext/cms/navigation', name: 'cardnext_admin_cms_menus', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/cardnext/cms/menu/index.html.twig', ['menus' => $this->entityManager->getRepository(CmsMenu::class)->findBy([], ['code' => 'ASC'])]);
    }

    #[Route('/admin/cardnext/cms/navigation/{id}', name: 'cardnext_admin_cms_menu_items', methods: ['GET'])]
    public function items(CmsMenu $menu): Response
    {
        $items = $this->entityManager->getRepository(CmsMenuItem::class)->findBy(['menu' => $menu], ['position' => 'ASC', 'id' => 'ASC']);

        return $this->render('admin/cardnext/cms/menu/items.html.twig', ['menu' => $menu, 'items' => $items]);
    }

    #[Route('/admin/cardnext/cms/navigation/{menu}/items/new', name: 'cardnext_admin_cms_menu_item_new', methods: ['GET', 'POST'])]
    public function create(CmsMenu $menu, Request $request): Response
    {
        $item = new CmsMenuItem();
        $item->setMenu($menu);

        return $this->form($menu, $item, $request, true);
    }

    #[Route('/admin/cardnext/cms/navigation/{menu}/items/{id}/edit', name: 'cardnext_admin_cms_menu_item_edit', methods: ['GET', 'POST'])]
    public function edit(CmsMenu $menu, CmsMenuItem $item, Request $request): Response
    {
        if ($item->getMenu() !== $menu) {
            throw $this->createNotFoundException();
        }

        return $this->form($menu, $item, $request, false);
    }

    #[Route('/admin/cardnext/cms/navigation/{menu}/items/{id}/delete', name: 'cardnext_admin_cms_menu_item_delete', methods: ['POST'])]
    public function delete(CmsMenu $menu, CmsMenuItem $item, Request $request): Response
    {
        if ($item->getMenu() !== $menu || !$this->isCsrfTokenValid('delete-cms-menu-item-' . $item->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->addFlash('success', 'Menüpunkt wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_cms_menu_items', ['id' => $menu->getId()]);
    }

    private function form(CmsMenu $menu, CmsMenuItem $item, Request $request, bool $new): Response
    {
        $parents = array_values(array_filter($menu->getItems()->toArray(), static fn (CmsMenuItem $candidate): bool => $candidate !== $item));
        $form = $this->createForm(CmsMenuItemType::class, $item, ['locale_choices' => $this->localeChoices(), 'parent_choices' => $parents])->handleRequest($request);
        $valid = $form->isSubmitted() && $form->isValid();
        if ($valid) {
            if ($item->getTargetType() === CmsMenuItem::ROUTE && $item->getRouteName() !== null && $this->router->getRouteCollection()->get($item->getRouteName()) === null) {
                $form->get('routeName')->addError(new FormError('Diese Route existiert nicht.'));
                $valid = false;
            }
            if ($valid) {
                if ($new) {
                    $this->entityManager->persist($item);
                }
                $this->entityManager->flush();
                $this->addFlash('success', 'Menüpunkt wurde gespeichert.');

                return $this->redirectToRoute('cardnext_admin_cms_menu_items', ['id' => $menu->getId()]);
            }
        }

        return $this->render('admin/cardnext/cms/menu/form.html.twig', ['form' => $form, 'menu' => $menu, 'item' => $item]);
    }

    /** @return array<string, string> */
    private function localeChoices(): array
    {
        $choices = [];
        foreach ($this->entityManager->getRepository(Locale::class)->findBy([], ['code' => 'ASC']) as $locale) {
            if ($locale->getCode() !== null) {
                $choices[$locale->getName() ?? $locale->getCode()] = $locale->getCode();
            }
        }

        return $choices;
    }
}
