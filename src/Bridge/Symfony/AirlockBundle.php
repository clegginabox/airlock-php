<?php

declare(strict_types=1);

namespace Clegginabox\Airlock\Bridge\Symfony;

use Clegginabox\Airlock\Bridge\Symfony\DependencyInjection\AirlockExtension;
use Override;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AirlockBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new AirlockExtension();
        }

        assert($this->extension instanceof AirlockExtension);

        return $this->extension;
    }
}
