<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Cms\VideoEmbedResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VideoEmbedResolverTest extends TestCase
{
    #[DataProvider('youtubeUrls')]
    public function testYouTubeUrlsAreResolved(string $url): void
    {
        $embed = (new VideoEmbedResolver())->resolve('youtube', $url);

        self::assertNotNull($embed);
        self::assertSame('abcdefghijk', $embed->videoId);
        self::assertSame('https://www.youtube-nocookie.com/embed/abcdefghijk?controls=1', $embed->embedUrl);
    }

    /** @return iterable<string, array{string}> */
    public static function youtubeUrls(): iterable
    {
        yield 'watch' => ['https://www.youtube.com/watch?v=abcdefghijk&feature=share'];
        yield 'short URL' => ['https://youtu.be/abcdefghijk'];
        yield 'shorts' => ['https://www.youtube.com/shorts/abcdefghijk'];
    }

    #[DataProvider('vimeoUrls')]
    public function testVimeoUrlsAreResolved(string $url): void
    {
        $embed = (new VideoEmbedResolver())->resolve('vimeo', $url, true, false);

        self::assertNotNull($embed);
        self::assertSame('123456789', $embed->videoId);
        self::assertSame('https://player.vimeo.com/video/123456789?controls=0', $embed->embedUrl);
    }

    /** @return iterable<string, array{string}> */
    public static function vimeoUrls(): iterable
    {
        yield 'public URL' => ['https://vimeo.com/123456789'];
        yield 'player URL' => ['https://player.vimeo.com/video/123456789'];
    }

    #[DataProvider('unsafeUrls')]
    public function testUnsafeUrlsAreRejected(string $url): void
    {
        self::assertNull((new VideoEmbedResolver())->resolve('youtube', $url));
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeUrls(): iterable
    {
        yield 'foreign host' => ['https://evil.example/video'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/html,<iframe>'];
        yield 'host suffix attack' => ['https://youtube.com.evil.example/watch?v=abcdefghijk'];
    }

    public function testStandardYouTubeModeAndHiddenControlsAreExplicit(): void
    {
        $embed = (new VideoEmbedResolver())->resolve('youtube', 'https://youtu.be/abcdefghijk', false, false);

        self::assertNotNull($embed);
        self::assertSame('https://www.youtube.com/embed/abcdefghijk?controls=0', $embed->embedUrl);
    }
}
