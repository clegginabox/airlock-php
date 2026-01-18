<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Queue\Storage\Fifo;

use Clegginabox\Airlock\Queue\Storage\Fifo\InMemoryFifoQueueStore;
use PHPUnit\Framework\TestCase;

class InMemoryFifoQueueStoreTest extends TestCase
{
    private InMemoryFifoQueueStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryFifoQueueStore();
    }

    public function testItAddsSequentialItems(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');
        $this->store->addToBack('user_3');

        $this->assertEquals('user_1', $this->store->popFront());
        $this->assertEquals('user_2', $this->store->popFront());
        $this->assertEquals('user_3', $this->store->popFront());
    }

    public function testPopFrontReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->store->popFront());
    }

    public function testPeekFrontReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->store->peekFront());
    }

    public function testPeekFrontReturnsFirstItem(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');
        $this->store->addToBack('user_3');

        $this->assertEquals('user_1', $this->store->peekFront());

        $this->store->remove('user_1');
        $this->assertEquals('user_2', $this->store->peekFront());
    }

    public function testGetPositionReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->store->getPosition('user_1'));
    }

    public function testGetPositionReturnsCorrectPosition(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');
        $this->store->addToBack('user_3');

        $this->assertEquals(1, $this->store->getPosition('user_1'));
        $this->assertEquals(2, $this->store->getPosition('user_2'));
        $this->assertEquals(3, $this->store->getPosition('user_3'));

        $this->store->popFront();

        $this->assertEquals(1, $this->store->getPosition('user_2'));
        $this->assertEquals(2, $this->store->getPosition('user_3'));

        $this->store->addToBack('user_4');

        $this->assertEquals(1, $this->store->getPosition('user_2'));
        $this->assertEquals(2, $this->store->getPosition('user_3'));
        $this->assertEquals(3, $this->store->getPosition('user_4'));

        $this->store->popFront();
        $this->store->popFront();

        $this->assertEquals(1, $this->store->getPosition('user_4'));
    }

    public function testContainsReturnsFalseIfNotOnQueue(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');
        $this->store->addToBack('user_3');

        $this->assertFalse($this->store->contains('user_4'));
    }

    public function testContainsReturnsTrueIfOnQueue(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');

        $this->assertTrue($this->store->contains('user_2'));
    }

    public function testAddToBackReturnPosition(): void
    {
        $this->assertEquals(1, $this->store->addToBack('user_1'));
        $this->assertEquals(2, $this->store->addToBack('user_2'));
        $this->assertEquals(3, $this->store->addToBack('user_3'));
    }

    public function testAddToBackIgnoresDuplicates(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');
        $this->store->addToBack('user_1'); // Duplicate

        $this->assertEquals(1, $this->store->getPosition('user_1'));
        $this->assertEquals(2, $this->store->getPosition('user_2'));

        // Only two items, not three
        $this->assertEquals('user_1', $this->store->popFront());
        $this->assertEquals('user_2', $this->store->popFront());
        $this->assertNull($this->store->popFront());
    }

    public function testAddToBackDuplicateReturnsExistingPosition(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');

        $this->assertEquals(1, $this->store->addToBack('user_1')); // Already at position 1
    }

    public function testRemoveNonExistentIsIdempotent(): void
    {
        $this->store->addToBack('user_1');

        $this->store->remove('user_999'); // Should not throw

        $this->assertTrue($this->store->contains('user_1'));
    }

    public function testPeekFrontDoesNotRemove(): void
    {
        $this->store->addToBack('user_1');

        $this->assertEquals('user_1', $this->store->peekFront());
        $this->assertEquals('user_1', $this->store->peekFront()); // Still there
        $this->assertTrue($this->store->contains('user_1'));
    }

    public function testRemoveFromMiddle(): void
    {
        $this->store->addToBack('user_1');
        $this->store->addToBack('user_2');
        $this->store->addToBack('user_3');

        $this->store->remove('user_2');

        $this->assertEquals(1, $this->store->getPosition('user_1'));
        $this->assertNull($this->store->getPosition('user_2'));
        $this->assertEquals(2, $this->store->getPosition('user_3')); // Shifted up
    }
}
