<?php

declare(strict_types=1);

namespace App;

use App\Infrastructure\RedisFactory;
use App\Infrastructure\StatusStore;
use Redis;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/../config/framework.yaml');
        $container->import(__DIR__ . '/../config/services.yaml');

        // PHP equivalent of config/packages/framework.yaml
        $container->extension('framework', [
            'secret' => 'S0ME_SECRET',
        ]);

        $services = $container->services();
        $services
            ->load('App\\', __DIR__ . '/*')
            ->exclude([
                __DIR__ . '/GlobalLock/Handler.php',
                __DIR__ . '/RedisLotteryQueue/Handler.php',
                __DIR__ . '/GlobalLock/resources',
                __DIR__ . '/RedisLotteryQueue/resources',
                __DIR__ . '/resources',
            ])
            ->autowire()
            ->autoconfigure()
        ;

        $services->set(RedisFactory::class)->public()->autowire();
        $services->set(StatusStore::class)->public()->autowire();
        $services->set(Redis::class)
            ->class(Redis::class)
            ->factory([service(RedisFactory::class), 'create']);

        //$services->set(RedisStore::class)
            //->args([service(Redis::class)]);

        /*$services->set(LockFactory::class)
            ->args([service(RedisStore::class)]);

        $services->set(SymfonyLockSeal::class)
            ->args([
                'factory' => service(LockFactory::class),
                'resource' => 'examples:01-global-lock:single-flight',
                'ttlInSeconds' => 10,
                'autoRelease' => false,
            ]);

        $services->set(OpportunisticAirlock::class)
            ->args([service(SymfonyLockSeal::class)]);*/
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // load the routes defined as PHP attributes
        $routes->import(__DIR__ . '/Controller/', 'attribute');
        $routes->import(__DIR__ . '/GlobalLock/', 'attribute');
        $routes->import(__DIR__ . '/RedisLotteryQueue/', 'attribute');
    }
}
