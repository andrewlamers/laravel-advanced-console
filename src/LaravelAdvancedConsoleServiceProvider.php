<?php

namespace Andrewlamers\LaravelAdvancedConsole;
use Illuminate\Support\ServiceProvider;

class LaravelAdvancedConsoleServiceProvider extends ServiceProvider
{
    public function register()
    {

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
