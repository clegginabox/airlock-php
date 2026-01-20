<?php

declare(strict_types=1);

namespace App\GlobalLock;

use App\Infrastructure\ClientIdCookieSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class Controller extends AbstractController
{
    #[Route('/global-lock', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return new Response(
            file_get_contents(__DIR__ . '/resources/index.html')
        );
    }

    #[Route('/global-lock/start', methods: [Request::METHOD_POST])]
    public function start(Request $request, GlobalLockService $service): JsonResponse
    {
        $clientId = (string) $request->attributes->get(ClientIdCookieSubscriber::ATTRIBUTE);
        $result = $service->start($clientId);

        if (!$result->isAdmitted()) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Already processing (server-side lock held)',
                'clientId' => $clientId,
            ], Response::HTTP_CONFLICT);
        }

        return new JsonResponse([
            'ok' => true,
            'clientId' => $clientId,
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/global-lock/status', methods: [Request::METHOD_GET])]
    public function status(Request $request, GlobalLockService $service): JsonResponse
    {
        $clientId = $request->query->get('clientId');

        if (!is_string($clientId) || $clientId === '') {
            return new JsonResponse(['error' => 'Missing clientId'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($service->status($clientId));
    }

    #[Route('/global-lock/script.js', methods: [Request::METHOD_GET])]
    public function script(): Response
    {
        return new Response(
            file_get_contents(__DIR__ . '/resources/script.js'),
            Response::HTTP_OK,
            ['Content-Type' => 'application/javascript']
        );
    }
}
