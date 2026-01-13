<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

/**
 * Marker interface for seals backed by a remote/shared store.
 *
 * A RemoteSeal coordinates access across multiple processes,
 * hosts, or containers.
 *
 * Use this when you need correctness in distributed systems
 * (e.g. multiple PHP-FPM workers, CLI workers, or Kubernetes pods).
 */
interface RemoteSeal
{
}
