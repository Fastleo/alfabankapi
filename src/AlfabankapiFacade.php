<?php

namespace Agenta\Alfabankapi;

use Illuminate\Support\Facades\Facade;

/**
 * @see \7981620\Alfabankapi\Skeleton\SkeletonClass
 */
class AlfabankapiFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'alfabankapi';
    }
}
