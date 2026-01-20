<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Notifier;

use Clegginabox\Airlock\Notifier\NullAirlockNotifier;
use PHPUnit\Framework\TestCase;

class NullAirlockNotifierTest extends TestCase
{
    public function testNotifyDoesNothing(): void
    {
        $notifier = new NullAirlockNotifier();
        $notifier->notify('test-identifier', 'test-topic');

        $this->assertTrue(true);
    }
}
