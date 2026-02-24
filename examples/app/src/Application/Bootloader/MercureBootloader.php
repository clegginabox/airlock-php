<?php

declare(strict_types=1);

namespace App\Application\Bootloader;

use Clegginabox\Airlock\Bridge\Symfony\Mercure\SymfonyMercureHubFactory;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Symfony\Component\Mercure\HubInterface;

final class MercureBootloader extends Bootloader
{
    protected const SINGLETONS = [
        HubInterface::class => [self::class, 'createHub'],
    ];

    private function createHub(EnvironmentInterface $env): HubInterface
    {
        $hubUrl = (string) $env->get('MERCURE_HUB_URL', 'http://localhost/.well-known/mercure');
        $jwtSecret = (string) $env->get('MERCURE_JWT_SECRET', 'airlock-mercure-secret-32chars-minimum');

        return SymfonyMercureHubFactory::create($hubUrl, $jwtSecret);
    }
}
