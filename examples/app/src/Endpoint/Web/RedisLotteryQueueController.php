<?php

declare(strict_types=1);

namespace App\Endpoint\Web;

use App\Examples\RedisLotteryQueue\RedisLotteryQueue;
use App\Examples\RedisLotteryQueue\RedisLotteryQueueService;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Prototype\Traits\PrototypeTrait;
use Spiral\Router\Annotation\Route;
use Spiral\Session\SessionInterface;

class RedisLotteryQueueController
{
    use PrototypeTrait;

    public function __construct(private readonly RedisLotteryQueueService $service)
    {
    }

    #[Route(route: '/redis-lottery-queue', name: 'redis_lottery_queue_index')]
    public function index(): string
    {
        return $this->views->render('redis-lottery-queue/index', [
            'hubUrl'      => $this->service->getHubUrl(),
            'globalToken' => $this->service->getGlobalToken(),
            'globalTopic' => RedisLotteryQueue::NAME->value,
        ]);
    }

    #[Route(route: '/redis-lottery-queue/start', name: 'redis_lottery_queue_start')]
    public function start(ServerRequestInterface $request, SessionInterface $session): array
    {
        $clientId = $this->getClientId($request);
        $session->resume();
        $queueSession = $session->getSection('queue');

        $result = $this->service->start($clientId);

        // User is queued
        if (!$result->isAdmitted()) {
            return [
                'ok' => true,
                'status' => 'queued',
                'position' => $result->getPosition(),
                'clientId' => $clientId,
                'topic' => $this->service->getTopic($clientId),
                'hubUrl' => $this->service->getHubUrl(),
                'token' => $this->service->getSubscriberToken($clientId),
            ];
        }

        // User is admitted — store serialized key for later release, keyed by tab
        $token = $result->getToken();

        if ($token !== null) {
            $queueSession->set('token_' . $clientId, (string) $token);
        }

        return [
            'ok' => true,
            'status' => 'admitted',
            'clientId' => $clientId,
            'topic' => $this->service->getTopic($clientId),
            'hubUrl' => $this->service->getHubUrl(),
            'token' => $this->service->getSubscriberToken($clientId),
        ];
    }

    #[Route(route: '/redis-lottery-queue/release', name: 'redis_lottery_queue_release')]
    public function release(ServerRequestInterface $request, SessionInterface $session): array
    {
        $clientId = $this->getClientId($request);
        $session->resume();
        $queueSession = $session->getSection('queue');

        $token = $queueSession->get('token_' . $clientId);

        if ($token !== null) {
            $this->service->release($token);
            $queueSession->delete('token_' . $clientId);
        }

        return [
            'ok' => true,
        ];
    }

    #[Route(route: '/redis-lottery-queue/success', name: 'redis_lottery_queue_success')]
    public function success(): string
    {
        return $this->views->render('redis-lottery-queue/success');
    }

    private function getClientId(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('X-Client-Id') ?: 'anonymous';
    }
}
