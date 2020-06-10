<?php


namespace Andrewlamers\LaravelAdvancedConsole\Handlers;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;


class DumpHandler
{
    protected $command;
    protected $object;
    protected $config;
    protected $lines = [];
    protected $keys = [];

    public function __construct($object, $command, $keys = null)
    {
        $this->command = $command;
        $this->object = $object;

        if(is_array($keys)) {
            $this->keys = $keys;
        }
    }

    protected function getConfig($object) {
        $config = config('laravel-advanced-console.dump.' . $this->getClassName($object));
        $commandConfig = Arr::get($this->command->dumpConfig, $this->getClassName($object));

        if($commandConfig) {
            return $commandConfig;
        }

        return $config;
    }

    protected function getClassName($object) {
        return get_class($object);
    }

    protected function isCollection($object ) {
        return $object instanceof Collection;
    }

    protected function isEloquent($object) {
        return $object instanceof Model;
    }

    protected function formatEloquent($object) {
        if(!$this->keys) {
            $this->keys = $this->getConfig($object);
        }

        if($this->keys) {
            $values = [];
            $arr = $object->toArray();
            foreach($this->keys as $key) {
                $values[$key] = Arr::get($arr, $key);
            }
        } else {
            $values = $object->toArray();
        }

        return $values;
    }

    protected function formatObject() {
        if($this->isCollection($this->object)) {
            $values = [];
            foreach($this->object as $item) {
                if($this->isEloquent($item)) {
                    $values[] = $this->formatEloquent($item);
                }
            }
        }
        else if($this->isEloquent($this->object)) {
            $values = $this->formatEloquent($this->object);
        }
    }

    public function render() {
        if($this->isCollection($this->object)) {
            $this->renderObjectTable();
        }
        else if($this->isEloquent($this->object)) {
            $this->renderObject();
        }
    }

    protected function renderObject() {
        $values = $this->formatEloquent($this->object);

        $items = [];

        foreach($values as $key => $value) {
            $items[] = '<fg=cyan;bg=default>' . $key . '</>' . ': ' . $value;
        }

        $this->command->linef('<comment>%s</comment>: %s', $this->getClassName($this->object), implode(', ', $items));
    }

    protected function renderObjectTable() {
        $headers = null;
        $lines = [];
        $class = null;

        foreach($this->object as $object) {

            if($this->isEloquent($object)) {
                $class = $this->getClassName($object);
                $line = $this->formatEloquent($object);
                if(!$headers) {
                    $headers = array_keys($line);
                }
                $lines[] = $line;
            }
        }

        $this->command->linef('%s table', $class);
        $this->command->table($headers, $lines);
    }
}