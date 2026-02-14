<?php

declare(strict_types=1);

namespace App\RedisLotteryQueue;

enum RedisLotteryQueue: string
{
    case NAME = '05-redis-lottery-queue';
    case RESOURCE = 'examples:05-lottery';
    case QUEUE_KEY = 'airlock:examples:05-lottery:queue';
    case SET_KEY = 'airlock:examples:05-lottery:pool';
    case CANDIDATE_KEY = 'airlock:examples:05-lottery:candidate';
}
