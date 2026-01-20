<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ClientIdCookieSubscriber implements EventSubscriberInterface
{
    public const ATTRIBUTE = 'clientId';

    private const COOKIE_ATTRIBUTE = 'airlock.client_id_cookie';

    public function __construct(
        private readonly string $cookieName = 'airlock_demo_id',
        private readonly int $minLength = 8
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $clientId = $request->cookies->get($this->cookieName);

        if (!is_string($clientId) || strlen($clientId) < $this->minLength) {
            $bytes = max(4, (int) ceil($this->minLength / 2));
            $clientId = bin2hex(random_bytes($bytes));

            $cookie = Cookie::create($this->cookieName, $clientId)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withSecure($request->isSecure());

            $request->attributes->set(self::COOKIE_ATTRIBUTE, $cookie);
        }

        $request->attributes->set(self::ATTRIBUTE, $clientId);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $cookie = $request->attributes->get(self::COOKIE_ATTRIBUTE);

        if (!($cookie instanceof Cookie)) {
            return;
        }

        $event->getResponse()->headers->setCookie($cookie);
    }
}
