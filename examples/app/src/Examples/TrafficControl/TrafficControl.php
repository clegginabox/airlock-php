<?php

declare(strict_types=1);

namespace App\Examples\TrafficControl;

enum TrafficControl: string
{
    case RESOURCE_ALPHA = 'examples:tc:provider:alpha';
    case RESOURCE_BETA = 'examples:tc:provider:beta';
    case RESOURCE_GAMMA = 'examples:tc:provider:gamma';
    case DOWN_KEY_PREFIX = 'airlock:tc:down:';
}
