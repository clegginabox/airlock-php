<?php

namespace Clegginabox\Airlock\Seal;

use Stringable;

interface SealToken extends Stringable
{
    public function getResource(): string;
    public function getId(): string;
}
