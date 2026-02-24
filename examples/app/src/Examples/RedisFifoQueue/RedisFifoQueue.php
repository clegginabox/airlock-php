<?php

declare(strict_types=1);

namespace App\Examples\RedisFifoQueue;

enum RedisFifoQueue: string
{
    case NAME = 'redis-fifo-queue';
    case RESOURCE = 'examples:fifo';
    case QUEUE_KEY = 'airlock:examples:fifo:queue';
    case SET_KEY = 'airlock:examples:fifo:set';
    case LIST_KEY = 'airlock:examples:fifo:list';
    case CANDIDATE_KEY = 'airlock:examples:fifo:candidate';
    case RESERVATION_KEY_PREFIX = 'airlock:examples:fifo:reservation';
}
