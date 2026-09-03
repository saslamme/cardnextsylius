<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Cms\CmsImageUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CmsImageUploaderTest extends TestCase
{
    private string $projectDirectory;

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir() . '/cms-upload-' . bin2hex(random_bytes(5));
        mkdir($this->projectDirectory . '/public', 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDirectory)) {
            exec('rm -rf ' . escapeshellarg($this->projectDirectory));
        }
    }

    public function testUploadsValidatedPngWithGeneratedNameAndDeletesManagedFile(): void
    {
        $source = $this->projectDirectory . '/pixel.png';
        file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        $uploader = new CmsImageUploader($this->projectDirectory);
        $path = $uploader->upload(new UploadedFile($source, 'unsafe name.png', 'image/png', null, true));

        self::assertMatchesRegularExpression('#^uploads/cms/[a-f0-9]{40}\.png$#', $path);
        self::assertFileExists($this->projectDirectory . '/public/' . $path);
        $uploader->delete($path);
        self::assertFileDoesNotExist($this->projectDirectory . '/public/' . $path);
    }

    public function testNeverDeletesOutsideManagedDirectory(): void
    {
        $outside = $this->projectDirectory . '/outside.txt';
        file_put_contents($outside, 'keep');
        (new CmsImageUploader($this->projectDirectory))->delete('../outside.txt');

        self::assertFileExists($outside);
    }
}
