<?php

declare(strict_types=1);

namespace App\Command;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use App\Examples\RedisLotteryQueue\RedisLotteryQueueService;
use Psr\Log\LoggerInterface;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

#[AsCommand(
    name: 'bots:queue:lottery',
    description: 'Spawns bots onto the lottery queue'
)]
class LotteryQueueBotsCommand extends Command
{
    /**
     * @var string[]
     */
    private array $bots;

    public function __construct(
        private RedisLotteryQueueService $redisLotteryQueueService,
        private LoggerInterface $logger,
    ) {
        parent::__construct();

        $this->bots = [
            'R2-D2',
            'Eyebot',
            'Bender',
            'C3PO',
            'WALL-E',
            'T-800',
            'Techno Trousers',
            'Mister Handy',
            'Metal Gear Rex',
        ];
    }

    public function __invoke(): void
    {
        $futures = [];

        foreach ($this->bots as $bot) {
            $futures[] = async(function () use ($bot) {
                while (true) {
                    delay($this->jitter(5, 45));

                    $this->runBotCycle($bot);
                }
            });
        }

        [$errors, $results] = awaitAll($futures); // phpcs:ignore

        foreach ($errors as $error) {
            $this->logger->error($error->getMessage());
        }
    }

    private function runBotCycle(string $botId): void
    {
        $topic = $this->redisLotteryQueueService->getTopic($botId);
        $hubUrl = $this->getMercureHubUrl();

        $client = HttpClientBuilder::buildDefault();

        // Subscribe to Mercure SSE FIRST — prevents race condition where
        // your_turn is published between enter() and subscribe.
        $token = $this->redisLotteryQueueService->getSubscriberToken($botId);

        $request = new Request($hubUrl . '?topic=' . urlencode($topic));
        $request->setHeader('Accept', 'text/event-stream');
        $request->setHeader('Authorization', 'Bearer ' . $token);
        $request->setBodySizeLimit(0);
        $request->setTransferTimeout(0);
        $request->setInactivityTimeout(0);

        try {
            $response = $client->request($request);
            $body = $response->getBody();
        } catch (\Throwable $e) {
            $this->logger->warning("{$botId}: SSE connection failed: {$e->getMessage()}");
            $this->fallbackPollCycle($botId);

            return;
        }

        // Now enter the queue — SSE is already listening
        $entryResult = $this->redisLotteryQueueService->start($botId);

        if ($entryResult->isAdmitted()) {
            $body->close();
            $this->holdAndRelease($botId, (string) $entryResult->getToken());

            return;
        }

        // Queued — wait for "your_turn" on the SSE stream
        $notified = $this->waitForYourTurn($body, $botId);
        $body->close();

        if (!$notified) {
            // Stream closed without notification — check position as fallback
            $position = $this->redisLotteryQueueService->getPosition($botId);

            if ($position === null) {
                return; // Fell off queue, outer loop will re-enter
            }

            if ($position === 1) {
                $this->claimSlot($botId);
            }

            return;
        }

        // Notified — claim the slot
        $this->claimSlot($botId);
    }

    /**
     * Read the SSE stream until a "your_turn" event arrives.
     *
     * Returns true if notified, false if the stream closed unexpectedly.
     */
    private function waitForYourTurn(mixed $body, string $botId): bool
    {
        $buffer = '';

        while (null !== $chunk = $body->read()) { // phpcs:ignore
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $data = $this->parseSseEvent($rawEvent);

                if ($data !== null && ($data['event'] ?? null) === 'your_turn') {
                    return true;
                }
            }
        }

        // Stream ended without your_turn
        $this->logger->warning("{$botId}: SSE stream closed unexpectedly");

        return false;
    }

    /**
     * Parse an SSE event block into a decoded JSON payload.
     *
     * @return array<string, mixed>|null
     */
    private function parseSseEvent(string $raw): ?array
    {
        $data = '';

        foreach (explode("\n", $raw) as $line) {
            if (!str_starts_with($line, 'data:')) {
                continue;
            }

            $data .= ltrim(substr($line, 5));
        }

        if ($data === '') {
            return null;
        }

        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private function claimSlot(string $botId): void
    {
        $result = $this->redisLotteryQueueService->start($botId);

        if ($result->isAdmitted()) {
            $this->holdAndRelease($botId, (string) $result->getToken());

            return;
        }

        // Rare race: someone else grabbed the slot. Will retry on next cycle.
        $this->logger->info("{$botId}: claim failed (race), will retry next cycle");
    }

    private function holdAndRelease(string $botId, string $token): void
    {
        delay($this->jitter(5, 10));

        $this->redisLotteryQueueService->release($token);
    }

    /**
     * Fallback when Mercure SSE is unavailable — single poll-based cycle.
     */
    private function fallbackPollCycle(string $botId): void
    {
        $entryResult = $this->redisLotteryQueueService->start($botId);

        if ($entryResult->isAdmitted()) {
            $this->holdAndRelease($botId, (string) $entryResult->getToken());

            return;
        }

        // Wait briefly and try once more
        delay(5);
        $result = $this->redisLotteryQueueService->start($botId);

        if (!$result->isAdmitted()) {
            return;
        }

        $this->holdAndRelease($botId, (string) $result->getToken());
    }

    private function getMercureHubUrl(): string
    {
        // Inside Docker, use the internal hostname; falls back to public URL
        return getenv('MERCURE_HUB_URL') ?: $this->redisLotteryQueueService->getHubUrl();
    }

    private function jitter(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
