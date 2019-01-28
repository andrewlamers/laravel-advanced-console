<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 11/9/18
 * Time: 6:47 AM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CommandHistory
 *
 * @package Andrewlamers\LaravelAdvancedConsole\Models
 * @property CommandHistoryMetadata $metadata
 * @property CommandHistoryOutput $output
 */
class CommandHistory extends Model
{
    protected $fillable = [
        'command_name', 'start_time', 'end_time', 'running', 'completed', 'failed', 'process_id'
    ];

    public function metadata() {
        return $this->hasOne(CommandHistoryMetadata::class);
    }

    public function output() {
        return $this->hasOne(CommandHistoryOutput::class);
    }

    public function exception(\Exception $e) {
        $this->fail();
        $this->attributes['exception'] = $e->getMessage();
        $this->metadata->exception_trace = $e->getTraceAsString();
        $this->metadata->save();
        $this->save();
    }

    protected function setState($state) {
        $this->resetState();
        switch($state) {
            case "failed":
            case "completed":
            case "running":
                $this->attributes[$state] = true;
        }

        $this->save();
    }

    public function start($startTime) {
        $this->setAttribute('start_time', $startTime);
        $this->save();
    }

    public function end($endTime) {
        $this->setAttribute('end_time', $endTime);
        $this->save();
    }

    public function durationMs($milliseconds) {
        $this->setAttribute('duration_ms', $milliseconds);
    }

    public function fail() {
        $this->setState('failed');
    }

    public function complete() {
        $this->setState('completed');
    }

    public function running() {
        $this->setState('running');
    }

    public function resetState() {
        $this->attributes['completed'] = false;
        $this->attributes['running'] = false;
        $this->attributes['failed'] = false;
    }

    public function setStartTimeAttribute($value) {
        $this->attributes['start_time'] = $value;
    }

    public function setEndTimeAttribute($value) {
        $this->attributes['end_time'] = $value;
    }
}