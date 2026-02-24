<?php

declare(strict_types=1);

namespace App\Command;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use App\Examples\RedisFifoQueue\RedisFifoQueueService;
use Psr\Log\LoggerInterface;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

#[AsCommand(
    name: 'bots:queue:fifo',
    description: 'Spawns bots onto the FIFO queue'
)]
class FifoQueueBotsCommand extends Command
{
    /**
     * @var string[]
     */
    private array $bots;

    public function __construct(
        private RedisFifoQueueService $redisFifoQueueService,
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
            'Johnny 5',
            'GLaDOS',
            'Claptrap',
            'Marvin the Paranoid Android',
            'Baymax',
            'ED-209',
            'The Iron Giant',
            'K-2SO',
            'Legion',
            'Rosie the Robot',
            'Optimus Prime',
            'Data',
            'HK-47',
            'Dog',
            'BT-7274',
            'Tachikoma',
            'Bastion',
            'Astro Boy',
            'Mechagodzilla',
        ];
    }

    public function __invoke(): void
    {
        $futures = [];

        foreach ($this->bots as $bot) {
            $futures[] = async(function () use ($bot) {
                while (true) {
                    delay($this->jitter(30, 180));

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
        $topic = $this->redisFifoQueueService->getTopic($botId);
        $hubUrl = $this->getMercureHubUrl();

        $client = HttpClientBuilder::buildDefault();

        // Subscribe to Mercure SSE FIRST — prevents race condition where
        // your_turn is published between enter() and subscribe.
        $token = $this->redisFifoQueueService->getSubscriberToken($botId);

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
        $entryResult = $this->redisFifoQueueService->start($botId);

        if ($entryResult->isAdmitted()) {
            $body->close();
            $this->holdAndRelease((string) $entryResult->getToken());

            return;
        }

        // Queued — wait for "your_turn" on the SSE stream
        $claimNonce = $this->waitForYourTurn($body, $botId);
        $body->close();

        if ($claimNonce === null) {
            // Stream closed without notification — check position as fallback
            $reservationNonce = $this->redisFifoQueueService->getReservationNonce($botId);

            if ($reservationNonce !== null) {
                $this->claimSlot($botId, $reservationNonce);

                return;
            }

            $position = $this->redisFifoQueueService->getPosition($botId);

            if ($position === null) {
                return; // Fell off queue, outer loop will re-enter
            }

            return;
        }

        // Notified — claim the slot
        $this->claimSlot($botId, $claimNonce);
    }

    /**
     * Read the SSE stream until a "your_turn" event arrives.
     *
     * Returns claim nonce if notified, null if the stream closed unexpectedly.
     */
    private function waitForYourTurn(mixed $body, string $botId): ?string
    {
        $buffer = '';

        while (null !== $chunk = $body->read()) { // phpcs:ignore
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $data = $this->parseSseEvent($rawEvent);

                if ($data !== null && ($data['event'] ?? null) === 'your_turn') {
                    $claimNonce = $data['claimNonce'] ?? null;

                    if (!is_string($claimNonce) || $claimNonce === '') {
                        $this->logger->warning("{$botId}: your_turn arrived without claim nonce");

                        return null;
                    }

                    return $claimNonce;
                }
            }
        }

        // Stream ended without your_turn
        $this->logger->warning("{$botId}: SSE stream closed unexpectedly");

        return null;
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

    private function claimSlot(string $botId, string $claimNonce): void
    {
        $result = $this->redisFifoQueueService->claim($botId, $claimNonce);

        if ($result->isAdmitted()) {
            $this->holdAndRelease((string) $result->getToken());

            return;
        }

        // Rare race: someone else grabbed the slot. Will retry on next cycle.
        $this->logger->info("{$botId}: claim failed ({$result->getStatus()}), will retry next cycle");
    }

    private function holdAndRelease(string $token): void
    {
        delay($this->jitter(10, 60));

        $this->redisFifoQueueService->release($token);
    }

    /**
     * Fallback when Mercure SSE is unavailable — single poll-based cycle.
     */
    private function fallbackPollCycle(string $botId): void
    {
        $entryResult = $this->redisFifoQueueService->start($botId);

        if ($entryResult->isAdmitted()) {
            $this->holdAndRelease((string) $entryResult->getToken());

            return;
        }

        // Wait for reservation nonce then claim once.
        delay(5);
        $claimNonce = $this->redisFifoQueueService->getReservationNonce($botId);

        if ($claimNonce === null) {
            return;
        }

        $this->claimSlot($botId, $claimNonce);
    }

    private function getMercureHubUrl(): string
    {
        // Inside Docker, use the internal hostname; falls back to public URL
        return getenv('MERCURE_HUB_URL') ?: $this->redisFifoQueueService->getHubUrl();
    }

    private function jitter(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
