<?php

declare(strict_types=1);

use Spiral\Events\Processor\AttributeProcessor;

return [
    /**
     * -------------------------------------------------------------------------
     *  Listeners
     * -------------------------------------------------------------------------
     *
     * Listeners are registered via #[Listener] attributes on listener classes.
     * No need to duplicate them here.
     */
    'listeners' => [],

    /**
     * -------------------------------------------------------------------------
     *  Processors
     * -------------------------------------------------------------------------
     *
     * Array of all available processors.
     */
    'processors' => [
        AttributeProcessor::class,
    ],
];
