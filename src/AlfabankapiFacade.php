<?php

namespace Agenta\Alfabankapi;

use Illuminate\Support\Facades\Facade;

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
