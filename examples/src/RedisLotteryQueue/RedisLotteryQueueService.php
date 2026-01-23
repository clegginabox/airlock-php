<?php

declare(strict_types=1);

namespace App\RedisLotteryQueue;

use App\Factory\AirlockFactory;
use Clegginabox\Airlock\EntryResult;
use Clegginabox\Airlock\QueueAirlock;

final class RedisLotteryQueueService
{
    private QueueAirlock $airlock;

    public function __construct(
        private readonly AirlockFactory $airlockFactory,
    ) {
        $this->airlock = $this->airlockFactory->redisLotteryQueue();
    }

    public function start(string $clientId, int $holdSeconds = 15): EntryResult
    {
        return $this->tryAdmit($clientId, $holdSeconds);
    }

    public function check(string $clientId, int $holdSeconds = 15): EntryResult
    {
        return $this->tryAdmit($clientId, $holdSeconds);
    }

    private function tryAdmit(string $clientId, int $holdSeconds = 15): EntryResult
    {
        $result = $this->airlock->enter($clientId);

        if ($result->isAdmitted()) {
            $token = $result->getToken();
            return $result;

        }

        return $result;
    }
}
