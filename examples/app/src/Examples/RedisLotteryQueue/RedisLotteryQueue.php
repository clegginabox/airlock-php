<?php

declare(strict_types=1);

namespace App\Examples\RedisLotteryQueue;

enum RedisLotteryQueue: string
{
    case NAME = 'redis-lottery-queue';
    case RESOURCE = 'examples:lottery';
    case QUEUE_KEY = 'airlock:examples:lottery:queue';
    case SET_KEY = 'airlock:examples:lottery:pool';
    case CANDIDATE_KEY = 'airlock:examples:lottery:candidate';
}
