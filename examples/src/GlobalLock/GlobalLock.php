<?php

declare(strict_types=1);

namespace App\GlobalLock;

enum GlobalLock: string
{
    case NAME = '01-global-lock';
    case RESOURCE = 'examples:01-global-lock:single-flight';
}
