<?php
namespace Andrewlamers\LaravelAdvancedConsole\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class CommandConfig {

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function get($key) {
        return Arr::get($this->config, $key);
    }

    public function getConnection() {
        $connection = $this->get('database.connection');

        if(!$connection) {
            $connection = DB::getDefaultConnection();
        }

        return $connection;
    }
}