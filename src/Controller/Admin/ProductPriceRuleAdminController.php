<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Channel\Channel;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerGroup;
use App\Entity\Pricing\VariantTierPrice;
use App\Entity\Product\CustomerVariantPriceRule;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Entity\Product\VariantPriceRule;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ProductPriceRuleAdminController extends AbstractController
{
    #[Route('/admin/cardnext/products/{id}/price-rules', name: 'cardnext_admin_product_price_rule_index', methods: ['GET'])]
    public function index(Product $product, EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/cardnext/product_price_rule/index.html.twig', [
            'page_title' => 'B2B- & Staffelpreise',
            'product' => $product,
            'rules' => $this->findProductRules($product, $entityManager),
            'customer_rules' => $this->findCustomerRules($product, $entityManager),
            'tier_prices' => $this->findTierPrices($product, $entityManager),
            'customer_groups' => $entityManager->getRepository(CustomerGroup::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/admin/cardnext/products/{id}/price-rules', name: 'cardnext_admin_product_price_rule_create', methods: ['POST'])]
    public function create(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'create-product-price-rule-' . $product->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $variant = $this->resolveVariant($product, (string) $request->request->get('variant_code'), $entityManager);
        $channel = $this->resolveChannel($product, (string) $request->request->get('channel_code'), $entityManager);
        $customerGroupCode = trim((string) $request->request->get('customer_group_code'));
        if ($customerGroupCode === '') {
            throw new \InvalidArgumentException('Bitte eine Kundengruppe auswählen. Öffentliche Staffelpreise werden separat gepflegt.');
        }
        $this->assertCustomerGroupExists($customerGroupCode, $entityManager);

        $minQuantity = max(1, (int) $request->request->get('min_quantity', 1));
        $price = $this->priceToMinor((string) $request->request->get('price'));

        $existing = $entityManager->getRepository(VariantPriceRule::class)->findOneBy([
            'variant' => $variant,
            'channelCode' => $channel->getCode(),
            'customerGroupCode' => $customerGroupCode,
            'minQuantity' => $minQuantity,
        ]);

        if ($existing instanceof VariantPriceRule) {
            $this->addFlash('error', 'Für diese Variante, diesen Kanal, diese Kundengruppe und diese Menge existiert bereits eine Preisregel.');

            return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
        }

        $rule = new VariantPriceRule();
        $rule->setVariant($variant);
        $rule->setChannelCode((string) $channel->getCode());
        $rule->setCustomerGroupCode($customerGroupCode);
        $rule->setMinQuantity($minQuantity);
        $rule->setPrice($price);
        $rule->setEnabled($request->request->has('enabled'));

        $entityManager->persist($rule);
        $entityManager->flush();

        $this->addFlash('success', 'Preisregel wurde angelegt.');

        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/price-rules/{id}/update', name: 'cardnext_admin_product_price_rule_update', methods: ['POST'])]
    public function update(
        VariantPriceRule $rule,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $product = $rule->getVariant()->getProduct();
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'update-product-price-rule-' . $rule->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $newMinQuantity = max(1, (int) $request->request->get('min_quantity', $rule->getMinQuantity()));

        if ($newMinQuantity !== $rule->getMinQuantity()) {
            $duplicate = $entityManager->getRepository(VariantPriceRule::class)->findOneBy([
                'variant' => $rule->getVariant(),
                'channelCode' => $rule->getChannelCode(),
                'customerGroupCode' => $rule->getCustomerGroupCode(),
                'minQuantity' => $newMinQuantity,
            ]);

            if ($duplicate instanceof VariantPriceRule && $duplicate !== $rule) {
                $this->addFlash('error', 'Für die neue Mindestmenge existiert bereits eine Preisregel.');

                return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
            }
        }

        $rule->setMinQuantity($newMinQuantity);
        $rule->setPrice($this->priceToMinor((string) $request->request->get('price')));
        $rule->setEnabled($request->request->has('enabled'));

        $entityManager->flush();

        $this->addFlash('success', 'Preisregel wurde aktualisiert.');

        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/price-rules/{id}/delete', name: 'cardnext_admin_product_price_rule_delete', methods: ['POST'])]
    public function delete(
        VariantPriceRule $rule,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $product = $rule->getVariant()->getProduct();
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'delete-product-price-rule-' . $rule->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $entityManager->remove($rule);
        $entityManager->flush();

        $this->addFlash('success', 'Preisregel wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/products/{id}/customer-price-rules', name: 'cardnext_admin_customer_price_rule_create', methods: ['POST'])]
    public function createCustomerRule(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'create-customer-price-rule-' . $product->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $variant = $this->resolveVariant($product, (string) $request->request->get('variant_code'), $entityManager);
        $channel = $this->resolveChannel($product, (string) $request->request->get('channel_code'), $entityManager);
        $customer = $this->resolveCustomer((string) $request->request->get('customer_email'), $entityManager);

        if ($customer->getB2bProfile() === null) {
            $this->addFlash('error', 'Für individuelle Preise benötigt der Kunde ein B2B-Profil.');

            return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
        }

        $minQuantity = max(1, (int) $request->request->get('min_quantity', 1));
        $price = $this->priceToMinor((string) $request->request->get('price'));

        $existing = $entityManager->getRepository(CustomerVariantPriceRule::class)->findOneBy([
            'variant' => $variant,
            'customer' => $customer,
            'channelCode' => $channel->getCode(),
            'minQuantity' => $minQuantity,
        ]);

        if ($existing instanceof CustomerVariantPriceRule) {
            $this->addFlash('error', 'Für diesen Kunden, diese Variante, diesen Kanal und diese Menge existiert bereits ein individueller Preis.');

            return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
        }

        $rule = new CustomerVariantPriceRule();
        $rule->setVariant($variant);
        $rule->setCustomer($customer);
        $rule->setChannelCode((string) $channel->getCode());
        $rule->setMinQuantity($minQuantity);
        $rule->setPrice($price);
        $rule->setEnabled($request->request->has('enabled'));

        $entityManager->persist($rule);
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Individueller Preis für %s wurde angelegt.',
            (string) $customer->getEmail(),
        ));

        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/customer-price-rules/{id}/update', name: 'cardnext_admin_customer_price_rule_update', methods: ['POST'])]
    public function updateCustomerRule(
        CustomerVariantPriceRule $rule,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $product = $rule->getVariant()->getProduct();
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'update-customer-price-rule-' . $rule->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $newMinQuantity = max(1, (int) $request->request->get('min_quantity', $rule->getMinQuantity()));

        if ($newMinQuantity !== $rule->getMinQuantity()) {
            $duplicate = $entityManager->getRepository(CustomerVariantPriceRule::class)->findOneBy([
                'variant' => $rule->getVariant(),
                'customer' => $rule->getCustomer(),
                'channelCode' => $rule->getChannelCode(),
                'minQuantity' => $newMinQuantity,
            ]);

            if ($duplicate instanceof CustomerVariantPriceRule && $duplicate !== $rule) {
                $this->addFlash('error', 'Für die neue Mindestmenge existiert bereits ein individueller Kundenpreis.');

                return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
            }
        }

        $rule->setMinQuantity($newMinQuantity);
        $rule->setPrice($this->priceToMinor((string) $request->request->get('price')));
        $rule->setEnabled($request->request->has('enabled'));

        $entityManager->flush();

        $this->addFlash('success', 'Individueller Kundenpreis wurde aktualisiert.');

        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/customer-price-rules/{id}/delete', name: 'cardnext_admin_customer_price_rule_delete', methods: ['POST'])]
    public function deleteCustomerRule(
        CustomerVariantPriceRule $rule,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $product = $rule->getVariant()->getProduct();
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'delete-customer-price-rule-' . $rule->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $entityManager->remove($rule);
        $entityManager->flush();

        $this->addFlash('success', 'Individueller Kundenpreis wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/products/{id}/tier-prices', name: 'cardnext_admin_tier_price_create', methods: ['POST'])]
    public function createTierPrice(Product $product, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('create-tier-price-' . $product->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $variant = $this->resolveVariant($product, (string) $request->request->get('variant_code'), $entityManager);
        $channel = $this->resolveChannel($product, (string) $request->request->get('channel_code'), $entityManager);
        $quantity = (int) $request->request->get('min_quantity', 1);
        $existing = $entityManager->getRepository(VariantTierPrice::class)->findOneBy(['variant' => $variant, 'channelCode' => $channel->getCode(), 'minQuantity' => $quantity]);
        if ($existing instanceof VariantTierPrice) {
            $this->addFlash('error', 'Für Variante, Kanal und Mindestmenge existiert bereits ein öffentlicher Staffelpreis.');
            return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
        }
        $tier = new VariantTierPrice(); $tier->setVariant($variant); $tier->setChannelCode((string) $channel->getCode());
        $tier->setMinQuantity($quantity); $tier->setPrice($this->priceToMinor((string) $request->request->get('price')));
        $entityManager->persist($tier); $entityManager->flush(); $this->addFlash('success', 'Öffentlicher Staffelpreis wurde angelegt.');
        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/tier-prices/{id}/update', name: 'cardnext_admin_tier_price_update', methods: ['POST'])]
    public function updateTierPrice(VariantTierPrice $tier, Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = $tier->getVariant()->getProduct(); if (!$product instanceof Product) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('update-tier-price-' . $tier->getId(), (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $quantity = (int) $request->request->get('min_quantity', $tier->getMinQuantity());
        $duplicate = $entityManager->getRepository(VariantTierPrice::class)->findOneBy(['variant' => $tier->getVariant(), 'channelCode' => $tier->getChannelCode(), 'minQuantity' => $quantity]);
        if ($duplicate instanceof VariantTierPrice && $duplicate !== $tier) { $this->addFlash('error', 'Diese Mindestmenge existiert bereits.'); return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]); }
        $tier->setMinQuantity($quantity); $tier->setPrice($this->priceToMinor((string) $request->request->get('price'))); $entityManager->flush();
        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/tier-prices/{id}/delete', name: 'cardnext_admin_tier_price_delete', methods: ['POST'])]
    public function deleteTierPrice(VariantTierPrice $tier, Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = $tier->getVariant()->getProduct(); if (!$product instanceof Product) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('delete-tier-price-' . $tier->getId(), (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $entityManager->remove($tier); $entityManager->flush();
        return $this->redirectToRoute('cardnext_admin_product_price_rule_index', ['id' => $product->getId()]);
    }

    /** @return list<VariantTierPrice> */
    private function findTierPrices(Product $product, EntityManagerInterface $entityManager): array
    {
        $tiers = $entityManager->createQueryBuilder()->select('tier')->from(VariantTierPrice::class, 'tier')->join('tier.variant', 'variant')
            ->andWhere('variant.product = :product')->setParameter('product', $product)->orderBy('variant.code', 'ASC')
            ->addOrderBy('tier.channelCode', 'ASC')->addOrderBy('tier.minQuantity', 'ASC')->getQuery()->getResult();
        return is_array($tiers) ? array_values(array_filter($tiers, static fn (mixed $tier): bool => $tier instanceof VariantTierPrice)) : [];
    }

    /**
     * @return list<VariantPriceRule>
     */
    private function findProductRules(Product $product, EntityManagerInterface $entityManager): array
    {
        /** @var list<VariantPriceRule> $rules */
        $rules = $entityManager
            ->createQueryBuilder()
            ->select('rule')
            ->from(VariantPriceRule::class, 'rule')
            ->join('rule.variant', 'variant')
            ->andWhere('variant.product = :product')
            ->andWhere("rule.customerGroupCode <> ''")
            ->setParameter('product', $product)
            ->orderBy('variant.code', 'ASC')
            ->addOrderBy('rule.channelCode', 'ASC')
            ->addOrderBy('rule.customerGroupCode', 'ASC')
            ->addOrderBy('rule.minQuantity', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rules;
    }

    /**
     * @return list<CustomerVariantPriceRule>
     */
    private function findCustomerRules(Product $product, EntityManagerInterface $entityManager): array
    {
        /** @var list<CustomerVariantPriceRule> $rules */
        $rules = $entityManager
            ->createQueryBuilder()
            ->select('rule')
            ->from(CustomerVariantPriceRule::class, 'rule')
            ->join('rule.variant', 'variant')
            ->join('rule.customer', 'customer')
            ->andWhere('variant.product = :product')
            ->setParameter('product', $product)
            ->orderBy('customer.emailCanonical', 'ASC')
            ->addOrderBy('variant.code', 'ASC')
            ->addOrderBy('rule.channelCode', 'ASC')
            ->addOrderBy('rule.minQuantity', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rules;
    }

    private function resolveVariant(
        Product $product,
        string $variantCode,
        EntityManagerInterface $entityManager,
    ): ProductVariant {
        /** @var ProductVariant|null $variant */
        $variant = $entityManager->getRepository(ProductVariant::class)->findOneBy(['code' => trim($variantCode)]);

        if (!$variant instanceof ProductVariant || $variant->getProduct() !== $product) {
            throw new \InvalidArgumentException('Die ausgewählte Variante gehört nicht zu diesem Produkt.');
        }

        return $variant;
    }

    private function resolveChannel(
        Product $product,
        string $channelCode,
        EntityManagerInterface $entityManager,
    ): Channel {
        /** @var Channel|null $channel */
        $channel = $entityManager->getRepository(Channel::class)->findOneBy(['code' => trim($channelCode)]);

        if (!$channel instanceof Channel || !$product->hasChannel($channel)) {
            throw new \InvalidArgumentException('Der ausgewählte Kanal ist für dieses Produkt nicht aktiv.');
        }

        return $channel;
    }

    private function resolveCustomer(string $email, EntityManagerInterface $entityManager): Customer
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            throw new \InvalidArgumentException('Bitte eine Kunden-E-Mail-Adresse angeben.');
        }

        /** @var Customer|null $customer */
        $customer = $entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => $email]);

        if (!$customer instanceof Customer) {
            $customer = $entityManager->getRepository(Customer::class)->findOneBy(['email' => $email]);
        }

        if (!$customer instanceof Customer) {
            throw new \InvalidArgumentException(sprintf('Kunde "%s" wurde nicht gefunden.', $email));
        }

        return $customer;
    }

    private function assertCustomerGroupExists(string $customerGroupCode, EntityManagerInterface $entityManager): void
    {
        if ($customerGroupCode === '') {
            return;
        }

        $group = $entityManager->getRepository(CustomerGroup::class)->findOneBy(['code' => $customerGroupCode]);

        if (!$group instanceof CustomerGroup) {
            throw new \InvalidArgumentException(sprintf('Kundengruppe "%s" wurde nicht gefunden.', $customerGroupCode));
        }
    }

    private function priceToMinor(string $value): int
    {
        $value = trim(str_replace(['€', ' '], '', $value));
        if ($value === '') {
            throw new \InvalidArgumentException('Bitte einen Preis angeben.');
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Preisformat ungültig. Beispiel: 89,90');
        }

        return (int) round(((float) $value) * 100);
    }
}
