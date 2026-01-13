<?php

namespace Clegginabox\Airlock;

interface HealthCheckerInterface
{
    /**
     * @return float 0.0 (dead) to 1.0 (fully healthy)
     */
    public function getScore(): float;
}
