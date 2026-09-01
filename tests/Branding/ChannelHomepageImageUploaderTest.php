<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use App\Content\ChannelHomepageImageUploader;
use App\Content\ChannelHomepageImageUploadException;
use App\Entity\Content\ChannelHomepageContent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ChannelHomepageImageUploaderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/homepage-uploader-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/public', 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            exec('rm -rf ' . escapeshellarg($this->projectDir));
        }
    }

    /** @dataProvider validImages */
    public function testValidRasterUploadUsesVerifiedExtension(string $mime, string $extension, string $bytes): void
    {
        $content = new ChannelHomepageContent();
        $content->setHeroImageFile($this->file($bytes, 'untrusted.svg', $mime));
        (new ChannelHomepageImageUploader($this->projectDir))->upload($content);

        self::assertMatchesRegularExpression('#^uploads/channel-homepage/[a-f0-9]{40}\\.' . $extension . '$#', (string) $content->getHeroImagePath());
        self::assertFileExists($this->projectDir . '/public/' . $content->getHeroImagePath());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function validImages(): iterable
    {
        yield 'JPEG' => ['image/jpeg', 'jpg', self::decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EB//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EB//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EB//2Q==')];
        yield 'PNG' => ['image/png', 'png', self::decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')];
        yield 'WebP' => ['image/webp', 'webp', self::decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==')];
    }

    /** @dataProvider invalidImages */
    public function testInvalidAndUnsupportedImagesAreRejected(string $bytes, string $mime): void
    {
        $content = new ChannelHomepageContent();
        $content->setHeroImagePath('uploads/channel-homepage/existing.webp');
        $content->setHeroImageFile($this->file($bytes, 'attack.jpg', $mime));

        $this->expectException(ChannelHomepageImageUploadException::class);

        try {
            (new ChannelHomepageImageUploader($this->projectDir))->upload($content);
        } finally {
            self::assertSame('uploads/channel-homepage/existing.webp', $content->getHeroImagePath());
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidImages(): iterable
    {
        yield 'HTML disguised as JPEG' => ['<html>not an image</html>', 'image/jpeg'];
        yield 'SVG renamed as PNG' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml'];
        yield 'invalid WebP' => ['RIFF invalid WEBP', 'image/webp'];
    }

    public function testReplacementAndRemovalOnlyDeleteManagedUploads(): void
    {
        $directory = $this->projectDir . '/public/uploads/channel-homepage';
        mkdir($directory, 0755, true);
        file_put_contents($directory . '/old.png', 'old');
        $content = new ChannelHomepageContent();
        $content->setHeroImagePath('uploads/channel-homepage/old.png');
        $content->setHeroImageFile($this->file((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true), 'new.png', 'image/png'));
        $uploader = new ChannelHomepageImageUploader($this->projectDir);
        $uploader->upload($content);
        self::assertFileDoesNotExist($directory . '/old.png');

        $packaged = $this->projectDir . '/public/cardnext/homepage/hero-card-printer.webp';
        mkdir(dirname($packaged), 0755, true);
        file_put_contents($packaged, 'packaged');
        $content->setHeroImagePath('cardnext/homepage/hero-card-printer.webp');
        $content->setRemoveHeroImage(true);
        $uploader->upload($content);
        self::assertNull($content->getHeroImagePath());
        self::assertFileExists($packaged);
    }

    public function testOversizedImageIsRejectedBeforeAnyWrite(): void
    {
        $content = new ChannelHomepageContent();
        $content->setHeroImageFile($this->file(str_repeat('x', 5 * 1024 * 1024 + 1), 'large.jpg', 'image/jpeg'));
        $this->expectException(ChannelHomepageImageUploadException::class);
        (new ChannelHomepageImageUploader($this->projectDir))->upload($content);
    }

    private function file(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam($this->projectDir, 'upload');
        self::assertIsString($path);
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private static function decode(string $bytes): string
    {
        $decoded = base64_decode($bytes, true);
        if ($decoded === false) {
            throw new \LogicException('Invalid test fixture.');
        }

        return $decoded;
    }
}
