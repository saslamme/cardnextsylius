<?php

declare(strict_types=1);

namespace App\Content;

final class ChannelHomepageImageUploadException extends \RuntimeException
{
    public function __construct(public readonly string $field, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
