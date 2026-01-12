<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Exception;

use RuntimeException;

class LeaseExpiredException extends RuntimeException
{
    public function __construct(string $token, string $message)
    {
        parent::__construct(\sprintf('The lease "%s" has expired: %s.', $token, $message));
    }
}
