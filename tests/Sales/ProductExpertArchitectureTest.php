<?php
declare(strict_types=1);
namespace App\Tests\Sales;
use App\Entity\Sales\ProductExpert;
use App\Entity\Sales\ProductExpertProduct;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
final class ProductExpertArchitectureTest extends TestCase
{
    public function testSalesAccessPrecedesGeneralAdminAccess(): void
    { $yaml = file_get_contents(__DIR__.'/../../config/packages/security.yaml'); self::assertIsString($yaml); self::assertLessThan(strpos($yaml, 'role: ROLE_ADMINISTRATION_ACCESS'), strpos($yaml, 'path: ^/admin/vertrieb')); }
    public function testPublicPageReusesTheCardnextProductCard(): void
    { $twig = file_get_contents(__DIR__.'/../../templates/shop/product_expert/show.html.twig'); self::assertIsString($twig); self::assertStringContainsString("component('cardnext:product:card'", $twig); self::assertStringContainsString('rel="canonical"', $twig); }

    public function testAdminFormUsesTheSyliusFormThemeAndStructuredCards(): void
    {
        $twig = file_get_contents(__DIR__.'/../../templates/admin/product_expert/form.html.twig');

        self::assertIsString($twig);
        self::assertStringContainsString("{% form_theme form '@SyliusAdmin/shared/form_theme.html.twig' %}", $twig);
        self::assertStringContainsString('<h3 class="card-title">Allgemein</h3>', $twig);
        self::assertStringContainsString('<h3 class="card-title">Inhalt</h3>', $twig);
        self::assertStringContainsString("'data-product-expert-name': ''", $twig);
        self::assertStringContainsString("'data-product-expert-slug': ''", $twig);
        self::assertStringContainsString('Sortiment verwalten', $twig);
    }

    public function testAssortmentUsesStimulusWithoutInlineJavaScript(): void
    {
        $twig = file_get_contents(__DIR__ . '/../../templates/admin/product_expert/assortment.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../assets/admin/controllers/product_expert_assortment_controller.js');

        self::assertIsString($twig);
        self::assertIsString($controller);
        self::assertStringNotContainsString('<script', $twig);
        self::assertStringContainsString('data-controller="product-expert-assortment"', $twig);
        self::assertStringContainsString('data-product-expert-assortment-target="results"', $twig);
        self::assertStringContainsString('data-product-expert-assortment-target="empty"', $twig);
        self::assertStringContainsString('data-product-expert-assortment-target="status"', $twig);
        self::assertStringContainsString('response.ok', $controller);
        self::assertStringContainsString('console.error', $controller);
    }

    public function testAssortmentSearchKeepsAllRequiredProductFiltersAndPayloadFields(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ProductExpertAssortmentController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('LOWER(translation.name) LIKE :query', $controller);
        self::assertStringContainsString('LOWER(product.code) LIKE :query', $controller);
        self::assertStringContainsString('LOWER(variant.code) LIKE :query', $controller);
        self::assertStringContainsString('LOWER(manufacturer.name) LIKE :query', $controller);
        self::assertStringContainsString("'enabled' => true", $controller);
        self::assertStringContainsString("'channel' => \$expert->getChannel()", $controller);
        self::assertStringContainsString('->setMaxResults(20)', $controller);
        self::assertStringContainsString("'alreadySelected' => \$alreadySelected", $controller);
        self::assertStringContainsString("'image' =>", $controller);
    }

    public function testProductExpertMappingsUseTheProductionColumnNames(): void
    {
        self::assertSame('admin_user_id', $this->attributeArguments(ProductExpert::class, 'adminUser', ORM\JoinColumn::class)['name']);
        self::assertSame('channel_id', $this->attributeArguments(ProductExpert::class, 'channel', ORM\JoinColumn::class)['name']);
        self::assertSame('display_name', $this->attributeArguments(ProductExpert::class, 'displayName', ORM\Column::class)['name']);
        self::assertSame('intro_text', $this->attributeArguments(ProductExpert::class, 'introText', ORM\Column::class)['name']);
        self::assertSame('created_at', $this->attributeArguments(ProductExpert::class, 'createdAt', ORM\Column::class)['name']);
        self::assertSame('updated_at', $this->attributeArguments(ProductExpert::class, 'updatedAt', ORM\Column::class)['name']);

        self::assertSame('expert_id', $this->attributeArguments(ProductExpertProduct::class, 'expert', ORM\JoinColumn::class)['name']);
        self::assertSame('product_id', $this->attributeArguments(ProductExpertProduct::class, 'product', ORM\JoinColumn::class)['name']);
        self::assertSame('created_at', $this->attributeArguments(ProductExpertProduct::class, 'createdAt', ORM\Column::class)['name']);
    }

    /** @param class-string $entity */
    private function attributeArguments(string $entity, string $property, string $attribute): array
    {
        $attributes = (new \ReflectionProperty($entity, $property))->getAttributes($attribute);
        self::assertCount(1, $attributes, sprintf('%s::$%s must have one %s attribute.', $entity, $property, $attribute));

        return $attributes[0]->getArguments();
    }
}
