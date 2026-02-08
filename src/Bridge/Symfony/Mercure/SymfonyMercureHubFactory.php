<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony\Mercure;

use Clegginabox\Airlock\Airlock;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;

class SymfonyMercureHubFactory
{
    /**
     * @param non-empty-string $jwtSecret
     */
    public static function create(string $hubUrl, string $jwtSecret): HubInterface
    {
        $jwtFactory = new LcobucciFactory($jwtSecret);
        $provider = new FactoryTokenProvider($jwtFactory, publish: ['*']);

        return new Hub($hubUrl, $provider);
    }

    /**
     * @param non-empty-string $jwtSecret
     */
    public static function createForAirlock(
        string $hubUrl,
        string $jwtSecret,
        Airlock $airlock,
        string $identifier,
    ): HubInterface {
        $topic = $airlock->getTopic($identifier);

        $jwtFactory = new LcobucciFactory($jwtSecret);
        $provider = new FactoryTokenProvider(
            $jwtFactory,
            subscribe: [$topic],
            publish: [$topic],
        );

        return new Hub($hubUrl, $provider);
    }
}
