<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 11/9/18
 * Time: 6:22 AM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Command;
use Illuminate\Support\Facades\DB;

class OutputService extends Service
{
    protected $outputBuffer = [];
    protected $canDisable = false;

    public function onWrite($line) {
        $this->bufferOutput($line);
    }

    public function getOutputBuffer() {
        return $this->outputBuffer;
    }

    public function bufferOutput($output) {
        $this->outputBuffer[] = $output;
    }
}