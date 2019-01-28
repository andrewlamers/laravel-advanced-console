<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 4/26/18
 * Time: 4:40 PM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Console\Formatter;


use Andrewlamers\LaravelAdvancedConsole\Command;
use \Symfony\Component\Console\Formatter\OutputFormatter as SymfonyOutputFormatter;

class OutputFormatter extends SymfonyOutputFormatter implements OutputFormatterInterface
{
    /**
     * @var Command $command
     */
    protected $command;

    public function format($message)
    {
        $this->command->executeCallbacks('onWrite', [$message]);
        return parent::format($message);
    }

    public function setCommand(Command $command) {
        $this->command = $command;
    }
}