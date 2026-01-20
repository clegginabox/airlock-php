<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Seal;

use Clegginabox\Airlock\Seal\WindowRateSeal;
use PHPUnit\Framework\TestCase;

class WindowRateSealTest extends TestCase
{
    public function testTryAcquire(): void
    {
        $seal = new WindowRateSeal();
        $this->assertNull($seal->tryAcquire());
    }
}
