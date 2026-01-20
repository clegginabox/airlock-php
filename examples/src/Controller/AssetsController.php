<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AssetsController
{
    #[Route('/style.css', methods: [Request::METHOD_GET])]
    public function style(): Response
    {
        return new Response(
            file_get_contents(dirname(__DIR__) . '/resources/style.css'),
            Response::HTTP_OK,
            ['Content-Type' => 'text/css']
        );
    }
}
