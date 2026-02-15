<?php

declare(strict_types=1);

namespace App\Endpoint\Web;

use App\Examples\TrafficControl\TrafficControlService;
use App\Factory\AirlockFactory;
use Psr\Http\Message\ServerRequestInterface;
use Redis;
use Spiral\Prototype\Traits\PrototypeTrait;
use Spiral\Router\Annotation\Route;

class TrafficControlController
{
    use PrototypeTrait;

    private readonly TrafficControlService $service;

    public function __construct(
        AirlockFactory $airlockFactory,
        Redis $redis,
    ) {
        $this->service = new TrafficControlService($airlockFactory, $redis);
    }

    #[Route(route: '/traffic-control', name: 'traffic_control_index')]
    public function index(): string
    {
        return $this->views->render('traffic-control/index');
    }

    #[Route(route: '/traffic-control/status', name: 'traffic_control_status')]
    public function status(): array
    {
        return [
            'providers' => $this->service->getProviders(),
        ];
    }

    #[Route(route: '/traffic-control/burst', name: 'traffic_control_burst')]
    public function burst(ServerRequestInterface $request): array
    {
        $count = (int) ($request->getQueryParams()['count'] ?? 10);
        $count = max(1, min(200, $count));
        $batchId = trim((string) ($request->getQueryParams()['batchId'] ?? ''));

        $batchId = $this->service->handleRequests($count, $batchId !== '' ? $batchId : null);

        return [
            'ok' => true,
            'batchId' => $batchId,
            'count' => $count,
        ];
    }

    #[Route(route: '/traffic-control/toggle', name: 'traffic_control_toggle')]
    public function toggle(ServerRequestInterface $request): array
    {
        $provider = $request->getQueryParams()['provider'] ?? '';

        if (!in_array($provider, ['alpha', 'beta', 'gamma'], true)) {
            return ['ok' => false, 'error' => 'Invalid provider'];
        }

        $isDown = $this->service->toggleDown($provider);

        return [
            'ok' => true,
            'provider' => $provider,
            'down' => $isDown,
        ];
    }

    #[Route(route: '/traffic-control/reset', name: 'traffic_control_reset')]
    public function reset(): array
    {
        $this->service->reset();

        return ['ok' => true];
    }
}
