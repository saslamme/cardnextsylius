<?php

declare(strict_types=1);

namespace App\Tests\ProductImage;

use App\Entity\Product\Product;
use App\Entity\Product\ProductImage;
use App\ProductImage\ProductImageImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ProductImageImporterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cardnext-image-import-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/sources', 0777, true);
        mkdir($this->directory . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->directory)));
        }
    }

    public function testMissingProductIsReported(): void
    {
        $result = $this->importer(null)->import($this->manifest('UNKNOWN;photo.jpg'), $this->directory . '/sources', true);

        self::assertSame(1, $result->counts['products_missing']);
        self::assertStringContainsString('UNKNOWN', $result->warnings[0]);
    }

    public function testValidFormatsAreImportedInManifestOrderAndSecondRunIsIdempotent(): void
    {
        $product = $this->product('PRODUCT');
        $this->image('first.jpg', \IMAGETYPE_JPEG);
        $this->image('second.png', \IMAGETYPE_PNG);
        $this->image('third.webp', \IMAGETYPE_WEBP);
        $manifest = $this->manifest('PRODUCT;first.jpg|second.png|third.webp');
        $importer = $this->importer($product);

        $first = $importer->import($manifest, $this->directory . '/sources');
        $second = $importer->import($manifest, $this->directory . '/sources');

        self::assertSame(3, $first->counts['images_valid']);
        self::assertSame(3, $product->getImages()->count());
        $positions = [];
        foreach ($product->getImages() as $image) {
            self::assertInstanceOf(ProductImage::class, $image);
            $positions[] = (int) $image->getPosition();
        }
        self::assertSame([0, 1, 2], $positions);
        self::assertSame(3, $second->counts['images_already_assigned']);
        self::assertSame(0, $second->counts['images_to_create']);
    }

    public function testInvalidMimeTraversalAndMissingFileAreRejected(): void
    {
        $product = $this->product('PRODUCT');
        file_put_contents($this->directory . '/sources/fake.jpg', 'not an image');

        $result = $this->importer($product)->import($this->manifest('PRODUCT;fake.jpg|../secret.jpg|missing.png'), $this->directory . '/sources', true);

        self::assertSame(2, $result->counts['images_invalid']);
        self::assertSame(1, $result->counts['images_missing']);
        self::assertCount(3, $result->warnings);
        self::assertSame(0, $product->getImages()->count());
    }

    public function testExistingImagesArePreservedByDefaultAndReplaceRemovesOnlyControlledFiles(): void
    {
        $product = $this->product('PRODUCT');
        $old = new ProductImage();
        $old->setPath('uploads/unmanaged.jpg');
        $old->setPosition(0);
        $product->addImage($old);
        $this->image('new.png', \IMAGETYPE_PNG);
        $manifest = $this->manifest('PRODUCT;new.png');
        $importer = $this->importer($product);

        $importer->import($manifest, $this->directory . '/sources');
        self::assertSame(2, $product->getImages()->count());
        $importer->import($manifest, $this->directory . '/sources', false, true);

        self::assertSame(1, $product->getImages()->count());
        $replacement = $product->getImages()->first();
        self::assertInstanceOf(ProductImage::class, $replacement);
        self::assertSame(0, $replacement->getPosition());
    }

    public function testFailedReplacementAndDryRunLeaveExistingImagesAndFilesUntouched(): void
    {
        $product = $this->product('PRODUCT');
        $old = new ProductImage();
        $old->setPath('media/cardnext/products/product/old.jpg');
        $old->setPosition(0);
        $product->addImage($old);
        $oldFile = $this->directory . '/public/' . $old->getPath();
        mkdir(dirname($oldFile), 0777, true);
        file_put_contents($oldFile, 'old');
        $this->image('valid.jpg', \IMAGETYPE_JPEG);
        $importer = $this->importer($product);

        $failed = $importer->import($this->manifest('PRODUCT;valid.jpg|missing.jpg'), $this->directory . '/sources', false, true);
        $dryRun = $importer->import($this->manifest('PRODUCT;valid.jpg'), $this->directory . '/sources', true, true);

        self::assertSame(1, $product->getImages()->count());
        self::assertFileExists($oldFile);
        self::assertSame(0, $failed->counts['products_to_replace']);
        self::assertSame(1, $dryRun->counts['products_to_replace']);
        self::assertFileDoesNotExist($this->directory . '/public/media/cardnext/products/product/01-' . hash_file('sha256', $this->directory . '/sources/valid.jpg') . '.jpg');
    }

    private function importer(?Product $product): ProductImageImporter
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($product);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->method('wrapInTransaction')->willReturnCallback(static function (callable $callback): mixed {
            return $callback();
        });

        return new ProductImageImporter($manager, new AsciiSlugger(), $this->directory);
    }

    private function product(string $code): Product
    {
        $product = new Product();
        $product->setCode($code);

        return $product;
    }

    private function manifest(string $row): string
    {
        $path = $this->directory . '/manifest-' . bin2hex(random_bytes(3)) . '.csv';
        file_put_contents($path, "product_code;images\n" . $row . "\n");

        return $path;
    }

    private function image(string $name, int $type): void
    {
        $image = imagecreatetruecolor(2, 2);
        self::assertNotFalse($image);
        match ($type) {
            \IMAGETYPE_JPEG => imagejpeg($image, $this->directory . '/sources/' . $name),
            \IMAGETYPE_PNG => imagepng($image, $this->directory . '/sources/' . $name),
            \IMAGETYPE_WEBP => file_put_contents($this->directory . '/sources/' . $name, base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==', true)),
            default => throw new \InvalidArgumentException('Unsupported test image type.'),
        };
        imagedestroy($image);
    }
}
