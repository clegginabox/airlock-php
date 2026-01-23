<?php

declare(strict_types=1);

namespace App\RedisLotteryQueue;

use App\Infrastructure\ClientIdCookieSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Controller extends AbstractController
{
    #[Route('/redis-lottery-queue', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return new Response(
            file_get_contents(__DIR__ . '/resources/index.html')
        );
    }

    #[Route('/redis-lottery-queue/script.js', methods: [Request::METHOD_GET])]
    public function script(): Response
    {
        return new Response(
            file_get_contents(__DIR__ . '/resources/script.js'),
            Response::HTTP_OK,
            ['Content-Type' => 'application/javascript']
        );
    }

    #[Route('/redis-lottery-queue/success', methods: [Request::METHOD_GET])]
    public function success(): Response
    {
        return new Response(
            file_get_contents(__DIR__ . '/resources/success.html')
        );
    }

    #[Route('/redis-lottery-queue/start', methods: [Request::METHOD_POST])]
    public function start(Request $request, RedisLotteryQueueService $service): JsonResponse
    {
        $clientId = (string) $request->attributes->get(ClientIdCookieSubscriber::ATTRIBUTE);
        $result = $service->start($clientId);

        if ($result->isAdmitted()) {
            return new JsonResponse([
                'ok' => true,
                'status' => 'admitted',
                'clientId' => $clientId,
            ]);
        }

        return new JsonResponse([
            'ok' => true,
            'status' => 'queued',
            'position' => $result->getPosition(),
            'clientId' => $clientId,
        ]);
    }

    #[Route('/redis-lottery-queue/check', methods: [Request::METHOD_POST])]
    public function check(Request $request, RedisLotteryQueueService $service): JsonResponse
    {
        $clientId = (string) $request->attributes->get(ClientIdCookieSubscriber::ATTRIBUTE);
        $result = $service->check($clientId);

        if ($result->isAdmitted()) {
            return new JsonResponse([
                'ok' => true,
                'status' => 'admitted',
                'clientId' => $clientId,
            ]);
        }

        return new JsonResponse([
            'ok' => true,
            'status' => 'queued',
            'position' => $result->getPosition(),
            'clientId' => $clientId,
        ]);
    }
}
