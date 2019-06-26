<?php
namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Command;
use DateTime;

class BenchmarkService extends Service
{
    /**
     * @var Command $command
     */
    protected $command;

    protected static $templates = ['%timestamp%', '%elapsedMs%', '%lastElapsedMs%', '%memoryUsage%'];

    protected $timestampFormat = 'Y-m-d h:i:s';

    protected $startTime;
    protected $endTime;
    protected $lastLineEnd;
    protected $lastElapsedTime = 0;

    public function initialize($input, $output): void {
        parent::initialize($input, $output);
        $this->start();
    }

    public function getPeakMemoryUsage() {
        return memory_get_peak_usage();
    }

    public function getCurrentMemoryUsage() {
        return memory_get_usage();
    }

    private function start(): void {
        $this->startTime = microtime(true);
    }

    private function end(): void {
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

    public function formatNumber($number): string {
        return number_format($number, 2);
    }

    public function formatMs($number): string {
        $ms = $number * 1000;
        return $this->formatNumber($ms);
    }

    /**
     * Converts seconds to a human readable string
     * @param $seconds
     * @return string
     */
    public function secondsToTimeString($seconds): string {

        $times = $this->msToTime($seconds);

        $values = [];

        foreach($times as $key => $value) {
            if($value > 0) {
                $values[] = $value . ' ' . $key;
            }
        }

        return implode(' ', $values);
    }

    /**
     * Converts seconds to days/hours/minutes/seconds/ms and returns an array with each value
     * @param $time - time in seconds
     * @return array
     */
    public function msToTime($time): array {
        $days = 0;
        $hours = 0;

        $seconds = floor($time);
        $minutes = floor(($seconds / 60) % 60);

        $milliseconds = floor(($time - $seconds) * 1000);

        if($seconds >= 3600) {
            $hours = floor($seconds / 3600);
        }

        if($seconds >= (3600 * 24)) {
            $days = floor($seconds / (24 * 3600));
            $hours -= ($days * 24);
        }

        $seconds %= 60;

        return [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'ms' => $milliseconds
        ];
    }

    public function afterRun(): void {
        $this->end();

        if($this->command->failed()) {
            $this->command->errorf('Command %s failed! Command ran for %s ms',
                $this->command->getName(), $this->secondsToTimeString($this->getTotalElapsedTime()));
        }
        else {

            $error_msg = '';
            $has_errors = false;
            $has_warnings = false;

            $message = sprintf('Command %s completed {error_msg} in %s. (%s ms). Total Memory Usage: %s',
                $this->command->getName(),
                $this->secondsToTimeString($this->getTotalElapsedTime()),
                $this->formatMs($this->getTotalElapsedTime()),
                $this->humanFileSize($this->getPeakMemoryUsage())
            );

            if($this->command->getWarningCount() > 0 && $this->command->getErrorCount() < 1) {
                $error_msg = sprintf('with %d warnings', $this->command->getWarningCount());
                $has_warnings = true;
            }
            else if($this->command->getErrorCount() > 0 && $this->command->getWarningCount() < 1) {
                $error_msg = sprintf('with %d errors', $this->command->getErrorCount());
                $has_errors = true;
            }
            else if($this->command->getErrorCount() > 0 && $this->command->getWarningCount() > 0) {
                $error_msg = sprintf('with %d warnings and %d errors', $this->command->getWarningCount(), $this->command->getErrorCount());
                $has_warnings = true;
                $has_errors = true;
            }

            $message = str_replace('{error_msg}', $error_msg, $message);


            if($has_errors || $has_warnings) {
                if(!$has_errors) {
                    $this->command->warn($message);
                } else {
                    $this->command->error($message);
                }
            }
            else {
                $this->command->info($message);
            }
        }
    }

    public function formatMemoryUsage(): string {
        return $this->humanFileSize($this->getCurrentMemoryUsage());
    }

    /**
     * @param $line
     * @param $style
     * @param $verbosity
     * @return mixed|string
     */
    public function formatLine($line, $style, $verbosity) {
        $line = '%timestamp%%lastElapsedMs%%memoryUsage%'.$line;
        $line = $this->formatTimestamp($line);
        return $line;
    }

    public function formatTimestamp($line) {
        $date = sprintf('[%s]', (new DateTime('now'))->format($this->timestampFormat));
        $elapsed = sprintf('[%sms]', $this->formatMs($this->getLastElapsedTime()));
        $memory = sprintf('[%s]', $this->formatMemoryUsage());

        return str_replace(static::$templates, [$date, null, $elapsed, $memory], $line);
    }

    public function setTimestampFormat($format): void {
        $this->timestampFormat = $format;
    }

    /**
     * Returns a human readable string for bytes
     * @param     $size
     * @param int $precision
     * @return string
     */
    public function humanFileSize($size, $precision = 2): string
    {
        $isNegative = FALSE;

        if ($size < 0) {
            $isNegative = TRUE;
            $size *= -1;
        }

        static $units = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $step = 1024;
        $i = 0;
        while (($size / $step) > 0.9) {
            $size /= $step;
            $i++;
        }

        $rounded = round($size, $precision);

        if ($isNegative) {
            $rounded *= -1;
        }

        return $rounded . $units[$i];
    }
}