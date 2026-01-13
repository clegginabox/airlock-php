<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\ReactPhp;

use Clegginabox\Airlock\AirlockInterface;
use React\Promise\PromiseInterface;
use RuntimeException;

use function React\Async\async;
use function React\Async\delay;

final readonly class AsyncAirlock
{
    public function __construct(private AirlockInterface $airlock)
    {
    }

    /**
     * Wait until admitted, then resolve with the seal token.
     *
     * @return PromiseInterface<string>
     */
    public function awaitAdmissionAsync(
        string $identifier,
        ?float $timeoutSeconds = 30.0,
        float $pollIntervalSeconds = 0.5,
    ): PromiseInterface {
        $airlock = $this->airlock;

        return async(function () use ($airlock, $identifier, $timeoutSeconds, $pollIntervalSeconds): string {
            $deadline = $timeoutSeconds !== null ? microtime(true) + $timeoutSeconds : null;
            $admitted = false;

            try {
                while (true) {
                    $result = $airlock->enter($identifier);

                    if ($result->isAdmitted()) {
                        $admitted = true;
                        return $result->getToken();
                    }

                    if ($deadline !== null && microtime(true) >= $deadline) {
                        throw new RuntimeException('Timed out waiting for admission');
                    }

                    // Non-blocking "sleep" that lets the React loop do other work.
                    delay($pollIntervalSeconds);
                }
            } finally {
                // If we never got admitted (timeout/cancel/error), ensure we leave the queue.
                if (!$admitted) {
                    $airlock->leave($identifier);
                }
            }
        })();
    }
}
