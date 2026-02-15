<?php

declare(strict_types=1);

namespace App\Endpoint\Web;

use App\Examples\GlobalLock\GlobalLockService;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Prototype\Traits\PrototypeTrait;
use Spiral\Router\Annotation\Route;

class GlobalLockController
{
    use PrototypeTrait;

    public function __construct(private readonly GlobalLockService $service)
    {
    }

    #[Route(route: '/global-lock', name: 'global_lock_index')]
    public function index(): string
    {
        return $this->views->render('global-lock/index');
    }

    #[Route(route: '/global-lock/status', name: 'global_lock_status')]
    public function status(): array
    {
        return [
            'locked' => $this->service->isLocked(),
        ];
    }

    #[Route(route: '/global-lock/start', name: 'global_lock_start')]
    public function start(ServerRequestInterface $request): array
    {
        $clientId = $this->getClientId($request);
        $result = $this->service->start($clientId);

        if (!$result->isAdmitted()) {
            return [
                'ok' => false,
                'error' => 'Already processing (server-side lock haseld)',
                'clientId' => $clientId,
            ];
        }

        return [
            'ok' => true,
            'clientId' => $clientId,
        ];
    }

    private function getClientId(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('X-Client-Id') ?: 'anonymous';
    }
}
