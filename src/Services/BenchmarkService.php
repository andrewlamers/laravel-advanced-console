<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 3/2/18
 * Time: 1:19 PM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Command;

class BenchmarkService extends Service
{
    /**
     * @var Command $command
     */
    protected $command;
    protected $templates = ['%timestamp%', '%elapsedMs%', '%lastElapsedMs%', '%memoryUsage%'];
    protected $timestampFormat = 'Y-m-d h:i:s';

    protected $startTime;
    protected $endTime;
    protected $lastLineEnd;
    protected $end;
    protected $lastElapsedTime = 0;

    public function initialize($input, $output) {
        parent::initialize($input, $output);
        $this->start();
    }

    private function getPeakMemoryUsage() {
        return memory_get_peak_usage();
    }

    private function getCurrentMemoryUsage() {
        return memory_get_usage();
    }

    private function start() {
        $this->startTime = microtime(true);
    }

    private function end() {
        $this->endTime = microtime(true);
    }

    public function getStartTime() {
        return $this->startTime;
    }

    public function getEndTime() {
        return $this->endTime;
    }

    public function getElapsedTime() {
        return microtime(true) - $this->startTime;
    }

    public function getTotalElapsedTime() {
        return $this->endTime - $this->startTime;
    }

    public function getLastElapsedTime() {
        if($this->lastElapsedTime === 0) {
            $this->lastElapsedTime = $this->startTime;
        }

        $elapsed = microtime(true) - $this->lastElapsedTime;

        $this->lastElapsedTime = microtime(true);

        return $elapsed;
    }

    public function formatNumber($number) {
        $number = $number * 1000;
        return number_format($number, 2, '.', '');
    }

    public function afterRun() {
        $this->end();

        if($this->command->failed()) {
            $this->command->error(
                sprintf('Command %s failed! Command ran for %d ms', $this->command->getName(), $this->formatNumber($this->getTotalElapsedTime()))
            );
        }
        else {
            $this->command->info(sprintf('Command %s finished in %d ms. Total Memory Usage: %s',
                    $this->command->getName(),
                    $this->formatNumber($this->getTotalElapsedTime()),
                    $this->humanFilesize($this->getPeakMemoryUsage())
                ));
        }
    }

    public function formatMemoryUsage() {
        return $this->humanFilesize($this->getCurrentMemoryUsage());
    }

    public function formatLine($line, $style, $verbosity) {
        $line = '%timestamp%%lastElapsedMs%%memoryUsage%'.$line;
        $line = $this->formatTimestamp($line);
        return $line;
    }

    public function formatTimestamp($line) {
        $date = sprintf('[%s]', (new \DateTime('now'))->format($this->timestampFormat));
        $elapsed = sprintf('[%sms]', $this->formatNumber($this->getLastElapsedTime()));
        $memory = sprintf('[%s]', $this->formatMemoryUsage());

        return str_replace($this->templates, [$date, null, $elapsed, $memory], $line);
    }

    public function setTimestampFormat($format) {
        $this->timestampFormat = $format;
    }

    public function humanFilesize($size, $precision = 2)
    {

        $isNegative = FALSE;

        if ($size < 0) {
            $isNegative = TRUE;
            $size = $size * -1;
        }

        static $units = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $step = 1024;
        $i = 0;
        while (($size / $step) > 0.9) {
            $size = $size / $step;
            $i++;
        }

        $rounded = round($size, $precision);

        if ($isNegative)
            $rounded = $rounded * -1;

        return $rounded . $units[$i];
    }
}