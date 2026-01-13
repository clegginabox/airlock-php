<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Seal;

class PortableRemoteLockSeal extends LockSeal implements RemoteSeal, PortableToken, RequiresTtl
{
}
