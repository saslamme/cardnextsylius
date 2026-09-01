<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use App\Branding\ChannelBrandingUploader;
use App\Branding\ChannelBrandingUploadException;
use App\Entity\Channel\Channel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

final class ChannelBrandingUploaderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/channel-branding-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/public', 0755, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->projectDir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->projectDir);
    }

    public function testSafeSvgIsSanitizedAndSavedWithRandomName(): void
    {
        $channel = new Channel();
        $channel->logoFile = $this->upload('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><rect width="100" height="20" fill="#123456"/></svg>', 'logo.svg', 'image/svg+xml');

        $this->uploader()->upload($channel);

        self::assertMatchesRegularExpression('#^uploads/channel-branding/[a-f0-9]{40}\.svg$#', (string) $channel->getLogoPath());
        self::assertStringContainsString('<rect', (string) file_get_contents($this->projectDir . '/public/' . $channel->getLogoPath()));
    }

    #[DataProvider('unsafeSvgProvider')]
    public function testUnsafeSvgContentIsNeverStoredInExecutableForm(string $svg, string $unsafe): void
    {
        $channel = new Channel();
        $channel->logoFile = $this->upload($svg, 'logo.svg', 'image/svg+xml');

        $this->uploader()->upload($channel);

        $stored = (string) file_get_contents($this->projectDir . '/public/' . $channel->getLogoPath());
        self::assertStringNotContainsStringIgnoringCase($unsafe, $stored);
    }

    /** @return iterable<string, array{string, string}> */
    public static function unsafeSvgProvider(): iterable
    {
        yield 'script and event handler' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><rect width="1" height="1"/></svg>', 'onload'];
        yield 'javascript URL' => ['<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>', 'javascript:'];
        yield 'remote image' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.example/image.svg"/></svg>', 'evil.example'];
        yield 'foreign object' => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">bad</div></foreignObject></svg>', 'foreignObject'];
    }

    public function testFakeSvgIsRejectedAndExistingPathIsPreserved(): void
    {
        $channel = new Channel();
        $channel->setLogoPath('uploads/channel-branding/existing.png');
        $channel->logoFile = $this->upload('<?php echo "bad"; ?>', 'fake.svg', 'image/svg+xml');

        $this->expectException(ChannelBrandingUploadException::class);

        try {
            $this->uploader()->upload($channel);
        } finally {
            self::assertSame('uploads/channel-branding/existing.png', $channel->getLogoPath());
        }
    }

    public function testValidPngStillWorks(): void
    {
        $channel = new Channel();
        $channel->logoFile = $this->upload((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='), 'logo.png', 'image/png');

        $this->uploader()->upload($channel);

        self::assertStringEndsWith('.png', (string) $channel->getLogoPath());
    }

    public function testUnsupportedFileIsRejectedWithoutChangingPath(): void
    {
        $channel = new Channel();
        $channel->setLogoPath('old.svg');
        $channel->logoFile = $this->upload('%PDF-1.4', 'document.pdf', 'application/pdf');

        $this->expectException(ChannelBrandingUploadException::class);

        try {
            $this->uploader()->upload($channel);
        } finally {
            self::assertSame('old.svg', $channel->getLogoPath());
        }
    }

    public function testAllUploadsArePreparedBeforeAnythingIsWritten(): void
    {
        $channel = new Channel();
        $channel->logoFile = $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', 'logo.svg', 'image/svg+xml');
        $channel->logoDarkFile = $this->upload('not an image', 'footer.js', 'application/javascript');

        try {
            $this->uploader()->upload($channel);
            self::fail('The unsafe footer upload should be rejected.');
        } catch (ChannelBrandingUploadException) {
            self::assertDirectoryDoesNotExist($this->projectDir . '/public/uploads/channel-branding');
            self::assertNull($channel->getLogoPath());
        }
    }

    public function testNoUploadKeepsEveryExistingPath(): void
    {
        $channel = new Channel();
        $channel->setLogoPath('logo.svg');
        $channel->setLogoDarkPath('dark.webp');
        $channel->setFaviconPath('favicon.png');

        $this->uploader()->upload($channel);

        self::assertSame(['logo.svg', 'dark.webp', 'favicon.png'], [$channel->getLogoPath(), $channel->getLogoDarkPath(), $channel->getFaviconPath()]);
    }

    public function testOversizedSvgFailsValidationCleanly(): void
    {
        $channel = new Channel();
        $channel->logoFile = $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><!--' . str_repeat('x', 2 * 1024 * 1024) . '--></svg>', 'large.svg', 'image/svg+xml');

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validateProperty($channel, 'logoFile');

        self::assertGreaterThan(0, $violations->count());
    }

    private function uploader(): ChannelBrandingUploader
    {
        return new ChannelBrandingUploader($this->projectDir);
    }

    private function upload(string $contents, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'branding-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
