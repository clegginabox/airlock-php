<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\RedisFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ResetController
{
    #[Route('/reset', methods: [Request::METHOD_POST, Request::METHOD_GET])]
    public function reset(RedisFactory $redisFactory): JsonResponse
    {
        $redis = $redisFactory->create();
        $redis->flushDB();

        return new JsonResponse([
            'ok' => true,
            'message' => 'Redis database flushed.',
        ], Response::HTTP_OK);
    }
}
