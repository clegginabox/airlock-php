<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * Marker interface for seals whose tokens are safe to serialize
 * and pass between processes or requests.
 *
 * Portable tokens can be stored in cookies, headers, or job payloads.
 */
interface PortableToken extends SealToken
{
}
