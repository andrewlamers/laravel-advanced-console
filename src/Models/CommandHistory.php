<?php
namespace Andrewlamers\LaravelAdvancedConsole\Models;

use Andrewlamers\LaravelAdvancedConsole\Facades\CommandConfig;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function __construct(array $attributes = [])
    {
        $this->setConnection(CommandConfig::getConnection());
        parent::__construct($attributes);
    }

    public function metadata(): HasOne {
        return $this->hasOne(CommandHistoryMetadata::class);
    }

    public function output(): HasOne {
        return $this->hasOne(CommandHistoryOutput::class);
    }

    public function exception(Exception $e): void {
        $this->fail();
        $this->attributes['exception'] = $e->getMessage();
        $this->metadata->exception_trace = $e->getTraceAsString();
        $this->metadata->save();
        $this->save();
    }

    public function updateLineCounts($counts): void {
        $this->attributes['warning_message_count'] = $counts['warning'];
        $this->attributes['error_message_count'] = $counts['error'];
        $this->attributes['info_message_count'] = $counts['info'];
        $this->attributes['line_message_count'] = $counts['line'];
    }

    protected function setState($state): void {
        $this->resetState();
        switch($state) {
            case 'failed':
            case 'completed':
            case 'running':
                $this->attributes[$state] = true;
        }

        $this->save();
    }

    public function start($startTime): void {
        $this->setAttribute('start_time', $startTime);
        $this->save();
    }

    public function end($endTime): void {
        $this->setAttribute('end_time', $endTime);
        $this->save();
    }

    public function durationMs($milliseconds): void {
        $this->setAttribute('duration_ms', $milliseconds);
    }

    public function peakMemoryUsage($value): void {
        $this->setAttribute('peak_memory_usage_bytes', $value);
    }

    public function fail(): void {
        $this->setState('failed');
    }

    public function complete(): void {
        $this->setState('completed');
    }

    public function running(): void {
        $this->setState('running');
    }

    public function resetState(): void {
        $this->attributes['completed'] = false;
        $this->attributes['running'] = false;
        $this->attributes['failed'] = false;
    }

    public function setStartTimeAttribute($value): void {
        $this->attributes['start_time'] = $value;
    }

    public function setEndTimeAttribute($value): void {
        $this->attributes['end_time'] = $value;
    }
}