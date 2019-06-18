<?php

namespace Andrewlamers\LaravelAdvancedConsole\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\Log\Writer
 */
class CommandConfig extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-advanced-console.commandConfig';
    }
}