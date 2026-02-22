<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Presence;

use Clegginabox\Airlock\Presence\NullPresenceProvider;
use PHPUnit\Framework\TestCase;

class NullPresenceProviderTest extends TestCase
{
    public function testAlwaysReturnsTrue(): void
    {
        $provider = new NullPresenceProvider();

        $this->assertTrue($provider->isConnected('user-1', '/waiting-room/user-1'));
        $this->assertTrue($provider->isConnected('anything', 'any-topic'));
    }
}
