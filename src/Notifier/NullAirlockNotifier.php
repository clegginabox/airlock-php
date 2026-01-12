<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Notifier;

final class NullAirlockNotifier implements AirlockNotifierInterface
{
    public function notify(string $identifier, string $topic): void
    {
    }
}
