<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Tests\Unit\Supervisor;

use Clegginabox\Airlock\Notifier\AirlockNotifierInterface;
use Clegginabox\Airlock\Presence\PresenceProviderInterface;
use Clegginabox\Airlock\Queue\EnumerableQueue;
use Clegginabox\Airlock\Reservation\ReservationStoreInterface;
use Clegginabox\Airlock\Supervisor\AirlockSupervisor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AirlockSupervisorTest extends TestCase
{
    private MockObject&EnumerableQueue $mockQueue;

    private MockObject&AirlockNotifierInterface $mockNotifier;

    protected function setUp(): void
    {
        $this->mockQueue = $this->createMock(EnumerableQueue::class);
        $this->mockNotifier = $this->createMock(AirlockNotifierInterface::class);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTickWithEmptyQueueDoesNothing(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn(null);

        $this->mockNotifier->expects($this->never())
            ->method('notify');

        $supervisor = new AirlockSupervisor($this->mockQueue, $this->mockNotifier);
        $result = $supervisor->tick();

        $this->assertFalse($result->hadActivity());
        $this->assertNull($result->notified);
        $this->assertSame([], $result->evicted);
    }

    public function testTickNotifiesNewCandidate(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('user-1');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', null);

        $supervisor = new AirlockSupervisor($this->mockQueue, $this->mockNotifier);
        $result = $supervisor->tick();

        $this->assertTrue($result->hadActivity());
        $this->assertSame('user-1', $result->notified);
        $this->assertSame([], $result->evicted);
    }

    public function testTickDoesNotReNotifyWithinClaimWindow(): void
    {
        $this->mockQueue->expects($this->exactly(2))
            ->method('peek')
            ->willReturn('user-1');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', null);

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            claimWindowSeconds: 60,
        );

        $first = $supervisor->tick();
        $second = $supervisor->tick();

        $this->assertSame('user-1', $first->notified);
        $this->assertNull($second->notified);
        $this->assertFalse($second->hadActivity());
    }

    public function testTickEvictsCandidateAfterClaimWindow(): void
    {
        // Use claimWindowSeconds: 0 so cooldown expires immediately
        // peek() calls: 1st tick → 'user-1' (notify), 2nd tick → 'user-1' (evict), after evict → null
        $this->mockQueue->expects($this->exactly(3))
            ->method('peek')
            ->willReturnOnConsecutiveCalls('user-1', 'user-1', null);

        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('user-1');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', null);

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            claimWindowSeconds: 0,
        );

        // First tick: notify user-1
        $supervisor->tick();

        // Second tick: claimWindow=0, so cooldown is already expired → evict
        $result = $supervisor->tick();

        $this->assertSame(['user-1'], $result->evicted);
        $this->assertNull($result->notified);
    }

    public function testTickEvictsDisconnectedUsersWithPresenceProvider(): void
    {
        $mockPresence = $this->createMock(PresenceProviderInterface::class);

        $this->mockQueue->expects($this->once())
            ->method('all')
            ->willReturn(['user-1', 'user-2', 'user-3']);

        $mockPresence->expects($this->exactly(3))
            ->method('isConnected')
            ->willReturnMap([
                ['user-1', '/waiting-room/user-1', true],
                ['user-2', '/waiting-room/user-2', false],
                ['user-3', '/waiting-room/user-3', true],
            ]);

        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('user-2');

        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('user-1');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', null);

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            presenceProvider: $mockPresence,
        );

        $result = $supervisor->tick();

        $this->assertSame(['user-2'], $result->evicted);
        $this->assertSame('user-1', $result->notified);
    }

    public function testTickSkipsPresenceCheckWhenNoProviderGiven(): void
    {
        $this->mockQueue->expects($this->never())
            ->method('all');

        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('user-1');

        $this->mockNotifier->expects($this->once())
            ->method('notify');

        $supervisor = new AirlockSupervisor($this->mockQueue, $this->mockNotifier);
        $supervisor->tick();
    }

    public function testTickEvictsThenNotifiesNextCandidate(): void
    {
        // peek() calls: 1st tick → 'stale-user' (notify), 2nd tick → 'stale-user' (evict), after evict → 'next-user'
        $this->mockQueue->expects($this->exactly(3))
            ->method('peek')
            ->willReturnOnConsecutiveCalls('stale-user', 'stale-user', 'next-user');

        $matcher = $this->exactly(2);
        $this->mockNotifier->expects($matcher)
            ->method('notify')
            ->willReturnCallback(function (string $identifier, string $topic, ?string $claimNonce) use ($matcher): void {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame('stale-user', $identifier),
                    2 => $this->assertSame('next-user', $identifier),
                    default => $this->fail('Unexpected notify call #' . $matcher->numberOfInvocations()),
                };
                $this->assertNull($claimNonce);
            });

        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('stale-user');

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            claimWindowSeconds: 0,
        );

        // First tick: notify stale-user
        $supervisor->tick();

        // Second tick: cooldown expired → evict stale-user, notify next-user
        $result = $supervisor->tick();

        $this->assertSame(['stale-user'], $result->evicted);
        $this->assertSame('next-user', $result->notified);
    }

    public function testTickSkipsNotificationWhenCandidateCannotClaimYet(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('user-1');

        $this->mockNotifier->expects($this->never())
            ->method('notify');

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            canNotifyCandidate: static fn (): bool => false,
        );

        $result = $supervisor->tick();

        $this->assertNull($result->notified);
        $this->assertSame([], $result->evicted);
    }

    public function testTickDoesNotEvictWhenClaimWindowExpiredButCandidateStillCannotClaim(): void
    {
        $this->mockQueue->expects($this->exactly(4))
            ->method('peek')
            ->willReturnOnConsecutiveCalls('user-1', 'user-1', 'user-1', null);

        $this->mockQueue->expects($this->once())
            ->method('remove');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', null);

        $attempt = 0;
        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            claimWindowSeconds: 0,
            canNotifyCandidate: static function () use (&$attempt): bool {
                $attempt++;

                return $attempt !== 2;
            },
        );

        $supervisor->tick(); // available => notify
        $second = $supervisor->tick(); // unavailable => skip, no eviction
        $third = $supervisor->tick(); // available => eviction now happens

        $this->assertNull($second->notified);
        $this->assertSame([], $second->evicted);

        $this->assertNull($third->notified);
        $this->assertSame(['user-1'], $third->evicted);
    }

    public function testTickCreatesReservationAndIncludesNonceInNotification(): void
    {
        $reservations = $this->createMock(ReservationStoreInterface::class);

        $this->mockQueue->expects($this->once())
            ->method('peek')
            ->willReturn('user-1');

        $reservations->expects($this->once())
            ->method('reserve')
            ->with('user-1', 10)
            ->willReturn('nonce-1');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', 'nonce-1');

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            reservations: $reservations,
        );

        $result = $supervisor->tick();

        $this->assertSame('user-1', $result->notified);
    }

    public function testTickClearsReservationOnEviction(): void
    {
        $reservations = $this->createMock(ReservationStoreInterface::class);

        $this->mockQueue->expects($this->exactly(3))
            ->method('peek')
            ->willReturnOnConsecutiveCalls('user-1', 'user-1', null);

        $this->mockQueue->expects($this->once())
            ->method('remove')
            ->with('user-1');

        $reservations->expects($this->once())
            ->method('reserve')
            ->with('user-1', 0)
            ->willReturn('nonce-1');

        $reservations->expects($this->once())
            ->method('clear')
            ->with('user-1');

        $this->mockNotifier->expects($this->once())
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', 'nonce-1');

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            claimWindowSeconds: 0,
            reservations: $reservations,
        );

        $supervisor->tick();
        $result = $supervisor->tick();

        $this->assertSame(['user-1'], $result->evicted);
        $this->assertNull($result->notified);
    }

    public function testResetClearsInternalState(): void
    {
        $this->mockQueue->expects($this->exactly(2))
            ->method('peek')
            ->willReturn('user-1');

        // After reset, the same candidate should be treated as new → notified again
        $this->mockNotifier->expects($this->exactly(2))
            ->method('notify')
            ->with('user-1', '/waiting-room/user-1', null);

        $supervisor = new AirlockSupervisor(
            $this->mockQueue,
            $this->mockNotifier,
            claimWindowSeconds: 60,
        );

        $supervisor->tick();
        $supervisor->reset();
        $result = $supervisor->tick();

        $this->assertSame('user-1', $result->notified);
    }
}
