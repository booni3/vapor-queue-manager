<?php

namespace Booni3\VaporQueueManager;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Booni3\VaporQueueManager\Skeleton\SkeletonClass
 */
class VaporQueueManagerFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'vapor-queue-manager';
    }
}
