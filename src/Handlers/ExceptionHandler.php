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
        if(method_exists($this->flattenedException, 'getAsString')) {
           return $this->flattenedException->getAsString();
        }

        return sprintf('Exception on line %s: %s', $this->exception->getLine(), $this->exception->getMessage());
    }

    public function render($output) {
        return (new Application)->renderException($this->exception, $output);
    }

    public function __toString()
    {
        return $this->getAsString();
    }
}