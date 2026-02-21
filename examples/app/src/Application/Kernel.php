<?php

declare(strict_types=1);

namespace App\Application;

use Spiral\Boot\Bootloader\CoreBootloader;
use Spiral\Bootloader as Framework;
use Spiral\Bootloader\Http\HttpBootloader;
use Spiral\Broadcasting\Bootloader\BroadcastingBootloader;
use Spiral\Cache\Bootloader\CacheBootloader;
use Spiral\Debug\Bootloader\DumperBootloader;
use Spiral\DotEnv\Bootloader\DotenvBootloader;
use Spiral\League\Event\Bootloader\EventBootloader;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\Nyholm\Bootloader\NyholmBootloader;
use Spiral\OpenTelemetry\Bootloader\OpenTelemetryBootloader;
use Spiral\Prototype\Bootloader\PrototypeBootloader;
use Spiral\Queue\Bootloader\QueueBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Spiral\Scaffolder\Bootloader\ScaffolderBootloader;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
use Spiral\Twig\Bootloader\TwigBootloader;
use Spiral\Views\Bootloader\ViewsBootloader;

/**
 * @psalm-suppress ClassMustBeFinal
 */
class Kernel extends \Spiral\Framework\Kernel
{
    #[\Override]
    public function defineSystemBootloaders(): array
    {
        return [
            CoreBootloader::class,
            DotenvBootloader::class,
            TokenizerListenerBootloader::class,

            DumperBootloader::class,
        ];
    }

    #[\Override]
    public function defineBootloaders(): array
    {
        return [
            // Logging and exceptions handling
            MonologBootloader::class,
            Bootloader\ExceptionHandlerBootloader::class,

            // Application specific logs
            Bootloader\LoggingBootloader::class,

            // RoadRunner
            RoadRunnerBridge\LoggerBootloader::class,
            RoadRunnerBridge\QueueBootloader::class,
            RoadRunnerBridge\HttpBootloader::class,
            RoadRunnerBridge\CacheBootloader::class,

            // Core Services
            Framework\SnapshotsBootloader::class,

            // Security and validation
            Framework\Security\EncrypterBootloader::class,
            Framework\Security\FiltersBootloader::class,
            Framework\Security\GuardBootloader::class,

            // HTTP extensions
            HttpBootloader::class,
            Framework\Http\RouterBootloader::class,
            Framework\Http\JsonPayloadsBootloader::class,
            Framework\Http\CookiesBootloader::class,
            Framework\Http\SessionBootloader::class,
            Framework\Http\CsrfBootloader::class,
            Framework\Http\PaginationBootloader::class,

            // Views
            ViewsBootloader::class,
            TwigBootloader::class,

            // OTEL
            OpenTelemetryBootloader::class,

            // Queue
            QueueBootloader::class,

            // Events
            //EventsBootloader::class,
            EventBootloader::class,

            // Cache
            CacheBootloader::class,

            // Broadcasting (Centrifugo is called directly via HTTP API)
            BroadcastingBootloader::class,

            NyholmBootloader::class,

            // Console commands
            Framework\CommandBootloader::class,
            RoadRunnerBridge\CommandBootloader::class,
            ScaffolderBootloader::class,
            RoadRunnerBridge\ScaffolderBootloader::class,

            // Fast code prototyping
            PrototypeBootloader::class,

            // Configure route groups, middleware for route groups
            Bootloader\RoutesBootloader::class,
        ];
    }

    #[\Override]
    public function defineAppBootloaders(): array
    {
        return [
            Bootloader\RedisBootloader::class,
            Bootloader\ViteBootloader::class,

            // Application domain
            Bootloader\AppBootloader::class,
        ];
    }
}
