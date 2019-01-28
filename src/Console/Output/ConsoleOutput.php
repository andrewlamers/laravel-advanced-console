<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 4/26/18
 * Time: 5:07 PM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Console\Output;


use Symfony\Component\Console\Output\Output;

class ConsoleOutput extends Output
{
    public function doWrite($message, $newline)
    {
        parent::doWrite($message, $newline);
    }
}