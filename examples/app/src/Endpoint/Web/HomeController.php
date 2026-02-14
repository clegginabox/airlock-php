<?php

declare(strict_types=1);

namespace App\Endpoint\Web;

use Redis;
use Spiral\Prototype\Traits\PrototypeTrait;
use Spiral\Router\Annotation\Route;

/**
 * Simple home page controller. It renders home page template and also provides
 * an example of exception page.
 */
final class HomeController
{
    /**
     * Read more about Prototyping:
     * @link https://spiral.dev/docs/basics-prototype/#installation
     */
    use PrototypeTrait;

    public function __construct(private Redis $redis)
    {
    }

    #[Route(route: '/', name: 'index')]
    public function index(): string
    {
        return $this->views->render('home');
    }

    #[Route(route: '/reset', name: 'reset')]
    public function reset(): array
    {
        $this->redis->flushAll();

        return [
            'ok' => true,
            'message' => 'Redis database flushed.',
        ];
    }

    /**
     * Example of exception page.
     */
    #[Route(route: '/exception', name: 'exception')]
    public function exception(): never
    {
        throw new \Exception('This is a test exception.');
    }
}
