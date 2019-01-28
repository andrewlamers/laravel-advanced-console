<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 11/10/18
 * Time: 7:01 AM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Console\Formatter;


use Andrewlamers\LaravelAdvancedConsole\Command;

interface OutputFormatterInterface extends \Symfony\Component\Console\Formatter\OutputFormatterInterface
{
    public function setCommand(Command $command);
}