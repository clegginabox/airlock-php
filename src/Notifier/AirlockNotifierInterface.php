<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Notifier;

interface AirlockNotifierInterface
{
    public function notify(string $identifier, string $topic): void;
}
