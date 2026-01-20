<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Seal;

use Clegginabox\Airlock\Seal\CooldownSeal;
use PHPUnit\Framework\TestCase;

class CooldownSealTest extends TestCase
{
    public function testTryAcquire(): void
    {
        $seal = new CooldownSeal();
        $this->assertNull($seal->tryAcquire());
    }
}
