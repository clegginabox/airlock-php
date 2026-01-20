<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit;

use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\Seal\SealToken;
use PHPUnit\Framework\TestCase;

class EntryResultTest extends TestCase
{
    private SealToken $mockSealToken;

    public function setUp(): void
    {
        $this->mockSealToken = new class implements SealToken
        {
            public function getResource(): string
            {
                return 'resource';
            }

            public function getId(): string
            {
                return 'id';
            }

            public function __toString()
            {
                return 'token';
            }
        };
    }

    public function testAdmitted(): void
    {
        $entryResult = EntryResult::admitted($this->mockSealToken, 'topic');

        $this->assertTrue($entryResult->isAdmitted());
        $this->assertSame($this->mockSealToken, $entryResult->getToken());
        $this->assertNull($entryResult->getPosition());
        $this->assertSame('topic', $entryResult->getTopic());
    }

    public function testQueued(): void
    {
        $entryResult = EntryResult::queued(42, 'topic');

        $this->assertFalse($entryResult->isAdmitted());
        $this->assertNull($entryResult->getToken());
        $this->assertEquals(42, $entryResult->getPosition());
        $this->assertSame('topic', $entryResult->getTopic());
    }
}
