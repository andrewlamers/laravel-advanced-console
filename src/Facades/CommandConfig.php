<?php

namespace Andrewlamers\LaravelAdvancedConsole\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Andrewlamers\LaravelAdvancedConsole\Config\CommandConfig
 */
class CommandConfig extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-advanced-console.commandConfig';
    }
}