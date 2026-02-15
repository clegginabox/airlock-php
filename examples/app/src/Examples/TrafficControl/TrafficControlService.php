<?php

declare(strict_types=1);

namespace App\Examples\TrafficControl;

use App\Factory\AirlockFactory;
use Clegginabox\Airlock\Airlock;
use Clegginabox\Airlock\ReleasingAirlock;
use Ramsey\Uuid\Uuid;
use Redis;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

final class TrafficControlService
{
    /**
     * Compresses real-world workflow timing to keep demos responsive
     * while preserving heavy-tail behavior.
     */
    private const SIMULATION_TIME_SCALE = 0.6;

    /** @var string[] */
    private array $providers = ['alpha', 'beta', 'gamma'];

    /** @var array<string, array{rateLimit: int, concurrency: int|null}> */
    private array $providerConfig = [
        'alpha' => ['rateLimit' => 50, 'concurrency' => null],
        'beta' => ['rateLimit' => 50, 'concurrency' => 5],
        'gamma' => ['rateLimit' => 30, 'concurrency' => null],
    ];

    /**
     * @var array<string, Airlock>
     */
    private array $airlocks;

    private readonly CentrifugoClient $centrifugo;

    public function __construct(
        private readonly AirlockFactory $airlockFactory,
        private readonly Redis $redis,
    ) {
        $this->centrifugo = new CentrifugoClient();
        $this->airlocks['alpha'] = $this->airlockFactory->trafficControl('alpha');
        $this->airlocks['beta'] = $this->airlockFactory->trafficControl('beta');
        $this->airlocks['gamma'] = $this->airlockFactory->trafficControl('gamma');
    }

    /**
     * @return list<array{name: string, rateLimit: int, concurrency: int|null, down: bool}>
     */
    public function getProviders(): array
    {
        return array_map(fn(string $name) => [
            'name' => $name,
            'rateLimit' => $this->providerConfig[$name]['rateLimit'],
            'concurrency' => $this->providerConfig[$name]['concurrency'],
            'down' => $this->isDown($name),
        ], $this->providers);
    }

    public function isDown(string $provider): bool
    {
        return (bool) $this->redis->get(TrafficControl::DOWN_KEY_PREFIX->value . $provider);
    }

    public function toggleDown(string $provider): bool
    {
        $key = TrafficControl::DOWN_KEY_PREFIX->value . $provider;

        if ($this->redis->get($key)) {
            $this->redis->del($key);
            return false;
        }

        $this->redis->set($key, '1');
        return true;
    }

    /**
     * Fire $count concurrent async "mock LLM requests" using Amp.
     * Each request takes roughly 3–40 seconds (after scaling), with heavy-tail jitter.
     * via Centrifugo WebSocket in real-time.
     *
     * @return string The batch ID
     */
    public function handleRequests(int $count, ?string $batchId = null): string
    {
        $batchId ??= Uuid::uuid4()->toString();
        $futures = [];

        for ($i = 0; $i < $count; $i++) {
            $requestId = Uuid::uuid4()->toString();

            $futures[] = async(function () use ($requestId, $batchId) {
                // Small dispatch jitter avoids robotic "all started at once" feel.
                delay($this->randomFloat(0.15, 1.6) * self::SIMULATION_TIME_SCALE);

                $order = $this->providers;
                shuffle($order);

                foreach ($order as $provider) {
                    if ($this->isDown($provider)) {
                        continue;
                    }

                    $airlock = $this->airlocks[$provider];
                    $result = $airlock->enter($requestId);

                    if (!$result->isAdmitted()) {
                        continue;
                    }

                    $this->centrifugo->publish('traffic-control', json_encode([
                        'event' => 'admitted',
                        'batchId' => $batchId,
                        'request' => $requestId,
                        'provider' => $provider,
                    ]));

                    delay($this->simulatedLatencyInSeconds($provider));

                    if ($airlock instanceof ReleasingAirlock) {
                        $airlock->release($result->getToken());
                    }

                    $this->centrifugo->publish('traffic-control', json_encode([
                        'event' => 'completed',
                        'batchId' => $batchId,
                        'request' => $requestId,
                        'provider' => $provider,
                    ]));

                    return;
                }

                $this->centrifugo->publish('traffic-control', json_encode([
                    'event' => 'rejected',
                    'batchId' => $batchId,
                    'request' => $requestId,
                ]));
            });
        }

        awaitAll($futures);

        $this->centrifugo->publish('traffic-control', json_encode([
            'event' => 'batch_complete',
            'batchId' => $batchId,
            'count' => $count,
        ]));

        return $batchId;
    }

    public function reset(): void
    {
        foreach ($this->providers as $provider) {
            $this->redis->del(TrafficControl::DOWN_KEY_PREFIX->value . $provider);
        }
    }

    private function simulatedLatencyInSeconds(string $provider): float
    {
        $profile = match ($provider) {
            'alpha' => [
                'medianSeconds' => 10.5,
                'sigma' => 0.58,
                'tailChance' => 10,
                'tailMinSeconds' => 5.0,
                'tailMaxSeconds' => 14.0,
            ],
            'beta' => [
                'medianSeconds' => 14.0,
                'sigma' => 0.70,
                'tailChance' => 18,
                'tailMinSeconds' => 8.0,
                'tailMaxSeconds' => 24.0,
            ],
            'gamma' => [
                'medianSeconds' => 12.0,
                'sigma' => 0.63,
                'tailChance' => 14,
                'tailMinSeconds' => 6.0,
                'tailMaxSeconds' => 18.0,
            ],
            default => [
                'medianSeconds' => 11.5,
                'sigma' => 0.63,
                'tailChance' => 10,
                'tailMinSeconds' => 6.0,
                'tailMaxSeconds' => 16.0,
            ],
        };

        // Log-normal latency produces realistic clustered completions with a long tail.
        $duration = $profile['medianSeconds'] * exp($profile['sigma'] * $this->sampleStandardNormal());

        if (random_int(1, 100) <= $profile['tailChance']) {
            $duration += $this->randomFloat($profile['tailMinSeconds'], $profile['tailMaxSeconds']);
        }

        $duration *= self::SIMULATION_TIME_SCALE;

        return max(3.0, min(40.0, $duration));
    }

    private function sampleStandardNormal(): float
    {
        // Box-Muller transform
        $u1 = max($this->randomFloat(0.0, 1.0), 1e-12);
        $u2 = $this->randomFloat(0.0, 1.0);

        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    private function randomFloat(float $min, float $max): float
    {
        return $min + ($max - $min) * (random_int(0, PHP_INT_MAX) / PHP_INT_MAX);
    }
}
