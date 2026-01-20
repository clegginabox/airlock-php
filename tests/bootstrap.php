<?php

// phpcs:ignoreFile Generic.Files.SideEffects
// phpcs:ignoreFile SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

declare(strict_types=1);

use Testcontainers\Modules\RedisContainer;
use Testcontainers\Container\StartedGenericContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

$_ENV['REDIS_URL'] = "redis://redis:6379";
