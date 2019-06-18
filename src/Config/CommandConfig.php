<?php
namespace Andrewlamers\LaravelAdvancedConsole\Config;

use Illuminate\Support\Facades\DB;

class CommandConfig {

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function get($key) {
        return array_get($this->config, $key);
    }

    public function getConnection() {
        $connection = $this->get('database.connection');

        if(!$connection) {
            $connection = DB::getDefaultConnection();
        }

        return $connection;
    }
}