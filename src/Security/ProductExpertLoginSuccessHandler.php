<?php
declare(strict_types=1);
namespace App\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
final class ProductExpertLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly UrlGeneratorInterface $router) {}
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    { $roles = $token->getRoleNames(); $route = in_array('ROLE_PRODUCT_EXPERT', $roles, true) && !in_array('ROLE_ADMINISTRATION_ACCESS', $roles, true) ? 'cardnext_admin_product_expert_assortment' : 'sylius_admin_dashboard'; return new RedirectResponse($this->router->generate($route)); }
}
