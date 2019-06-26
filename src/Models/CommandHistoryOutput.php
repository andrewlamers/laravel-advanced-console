<?php

namespace Andrewlamers\LaravelAdvancedConsole\Models;

use Andrewlamers\LaravelAdvancedConsole\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CommandHistoryOutput
 *
 * @package Andrewlamers\LaravelAdvancedConsole\Models
 * @property Command $command
 * @property string $output
 * @property int $command_history_id
 * @property int $id
 */
class CommandHistoryOutput extends Model
{
    protected $table = 'command_history_output';

    public function command() {
        return $this->belongsTo(CommandHistory::class);
    }
}
