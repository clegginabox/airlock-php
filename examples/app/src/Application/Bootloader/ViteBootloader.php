<?php

// phpcs:ignoreFile SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

declare(strict_types=1);

namespace App\Application\Bootloader;

use App\Application\Twig\ViteExtension;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Twig\Bootloader\TwigBootloader;

final class ViteBootloader extends Bootloader
{
    public function boot(TwigBootloader $twig, DirectoriesInterface $dirs): void
    {
        $publicPath = rtrim((string) $dirs->get('public'), '/');
        $isDev = filter_var($_ENV['VITE_DEV'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        $twig->addExtension(new ViteExtension($publicPath, $isDev));
    }
}
