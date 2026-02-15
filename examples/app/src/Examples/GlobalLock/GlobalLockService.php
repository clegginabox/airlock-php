<?php

declare(strict_types=1);

namespace App\Examples\GlobalLock;

use App\Factory\AirlockFactory;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\Seal\SealToken;
use Redis;

final readonly class GlobalLockService
{
    public function __construct(
        private AirlockFactory $airlockFactory,
        private Redis $redis,
    ) {
    }

    public function isLocked(): bool
    {
        return $this->redis->exists(GlobalLock::RESOURCE->value) > 0;
    }

    public function start(string $clientId, int $durationSeconds = 10): EntryResult
    {
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
