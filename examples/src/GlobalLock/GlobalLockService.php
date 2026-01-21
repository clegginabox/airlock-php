<?php

declare(strict_types=1);

namespace App\GlobalLock;

use App\Factory\AirlockFactory;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\Seal\SealToken;

final readonly class GlobalLockService
{
    public function __construct(private AirlockFactory $airlockFactory)
    {
    }

    public function start(string $clientId, int $durationSeconds = 10): EntryResult
    {
        $envDuration = getenv('GLOBAL_LOCK_TIMEOUT');
        if ($envDuration !== false && is_numeric($envDuration)) {
            $durationSeconds = max(1, (int) $envDuration);
        }

        $airlock = $this->airlockFactory->globalLock($durationSeconds);

        $result = $airlock->enter($clientId);

        if ($result->isAdmitted()) {
            $token = $result->getToken();
            assert($token instanceof SealToken);

            try {
                // Do a long running task here
                sleep($durationSeconds);
            } finally {
                $airlock->release($token);
            }
        }

        return $result;
    }
}
