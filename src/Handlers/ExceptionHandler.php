<?php
namespace Andrewlamers\LaravelAdvancedConsole\Handlers;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Console\Application;

class ExceptionHandler {

    protected $exception;
    protected $flattenedException;

    public function __construct(\Exception $e)
    {
        $this->exception = $e;
        $this->flattenedException = FlattenException::create($e);
    }

    public function getAsString() {
        return $this->flattenedException->getAsString();
    }

    public function render($output) {
        return (new Application)->renderException($this->exception, $output);
    }

    public function __toString()
    {
        return $this->getAsString();
    }
}