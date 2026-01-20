<?php

declare(strict_types=1);

namespace App\GlobalLock;

use App\Factory\AirlockFactory;
use App\Infrastructure\JobQueue;
use App\Infrastructure\StatusStore;
use Clegginabox\Airlock\EntryResult;

final readonly class GlobalLockService
{
    public function __construct(
        private AirlockFactory $airlockFactory,
        private JobQueue $jobs,
        private StatusStore $statusStore
    ) {
    }

    public function start(string $clientId, int $durationSeconds = 10): EntryResult
    {
        $envDuration = getenv('GLOBAL_LOCK_TIMEOUT');
        if ($envDuration !== false && is_numeric($envDuration)) {
            $durationSeconds = max(1, (int) $envDuration);
        }

        $airlock = $this->airlockFactory->globalLock($durationSeconds);

        $result = $airlock->enter($clientId);

        if (!$result->isAdmitted()) {
            return $result;
        }

        $token = $result->getToken();
        if ($token === null) {
            return EntryResult::queued(-1, '');
        }

        $this->jobs->enqueue(GlobalLock::NAME->value, [
            'clientId' => $clientId,
            'durationSeconds' => $durationSeconds,
            'serializedKey' => (string) $token,
        ]);

        return $result;
    }

    public function status(string $clientId): array
    {
        $status = $this->statusStore->get(GlobalLock::NAME->value, $clientId);

        if ($status !== null) {
            return $status;
        }

        return [
            'state' => 'pending',
            'message' => 'Waiting for worker...',
        ];
    }
}
