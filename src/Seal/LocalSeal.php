<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * Marker interface for seals backed by local, process- or host-local storage.
 *
 * A LocalSeal only provides coordination within a single host
 * (or even a single process, depending on the backend).
 *
 * Suitable for cron guards, CLI tools, or single-node setups.
 */
interface LocalSeal extends Seal
{
}
