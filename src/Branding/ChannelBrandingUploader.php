<?php
declare(strict_types=1);
namespace App\Branding;

use App\Entity\Channel\Channel;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ChannelBrandingUploader
{
    public function __construct(private string $projectDir) {}
    public function upload(Channel $channel): void
    {
        $directory = $this->projectDir . '/public/uploads/channel-branding';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create branding upload directory "%s".', $directory));
        }

        foreach ([['logoFile', 'setLogoPath'], ['logoDarkFile', 'setLogoDarkPath'], ['faviconFile', 'setFaviconPath']] as [$property, $setter]) {
            $file = $channel->{$property};
            if (!$file instanceof UploadedFile) continue;
            $extension = match ($file->getMimeType()) { 'image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg', default => throw new \InvalidArgumentException('Unsupported branding image type.') };
            $name = bin2hex(random_bytes(20)).'.'.$extension;
            $file->move($directory, $name);
            $channel->{$setter}('uploads/channel-branding/'.$name);
            $channel->{$property} = null;
        }
    }
}
