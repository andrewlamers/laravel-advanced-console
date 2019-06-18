<?php

namespace Andrewlamers\LaravelAdvancedConsole;
use Andrewlamers\LaravelAdvancedConsole\Config\CommandConfig;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class LaravelAdvancedConsoleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('laravel-advanced-console.commandConfig', function (Application $app) {
            $config = $app['config']->get('laravel-advanced-console');

            return new CommandConfig($config);
        });
    }

    public function boot()
    {
        $this->publishes([$this->basePath('config/laravel-advanced-console.php') => config_path('laravel-advanced-console.php')]);
        $this->publishes([$this->basePath('migrations') => database_path('migrations')]);
    }

    public function basePath($path) {
        return realpath(__DIR__.'/../' . $path);
    }
}
