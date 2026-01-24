<?php

declare(strict_types=1);

namespace App\RedisLotteryQueue;

use App\Infrastructure\ClientIdCookieSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Controller extends AbstractController
{
    private const SESSION_TOKEN_KEY = 'airlock.redis_lottery.token';

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

    #[Route('/redis-lottery-queue/release', methods: [Request::METHOD_POST])]
    public function release(Request $request, RedisLotteryQueueService $service): Response
    {
        try {
            $session = $request->getSession();
        } catch (SessionNotFoundException) {
            return new JsonResponse(['ok' => false, 'error' => 'Session not available'], Response::HTTP_BAD_REQUEST);
        }

        $serializedToken = $session->get(self::SESSION_TOKEN_KEY);
        if (!is_string($serializedToken) || $serializedToken === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Missing airlock token'], Response::HTTP_BAD_REQUEST);
        }

        $service->release($serializedToken);
        $session->remove(self::SESSION_TOKEN_KEY);

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/redis-lottery-queue/start', methods: [Request::METHOD_POST])]
    public function start(Request $request, RedisLotteryQueueService $service): JsonResponse
    {
        $clientId = (string) $request->attributes->get(ClientIdCookieSubscriber::ATTRIBUTE);

        $result = $service->start($clientId);

        if ($result->isAdmitted()) {
            try {
                $session = $request->getSession();
                $token = $result->getToken();
                if ($token !== null) {
                    $session->set(self::SESSION_TOKEN_KEY, (string) $token);
                }
            } catch (SessionNotFoundException) {
                // Session not available; continue without persisting token.
            }

            return new JsonResponse([
                'ok' => true,
                'status' => 'admitted',
                'clientId' => $clientId,
                'topic' => $service->getTopic($clientId),
                'hubUrl' => $service->getHubUrl(),
                'token' => $service->getSubscriberToken($clientId),
            ]);
        }

        return new JsonResponse([
            'ok' => true,
            'status' => 'queued',
            'position' => $result->getPosition(),
            'clientId' => $clientId,
            'topic' => $service->getTopic($clientId),
            'hubUrl' => $service->getHubUrl(),
            'token' => $service->getSubscriberToken($clientId),
        ]);
    }
}
