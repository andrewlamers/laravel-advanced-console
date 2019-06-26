<?php

namespace Andrewlamers\LaravelAdvancedConsole\Models;

use Andrewlamers\LaravelAdvancedConsole\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CommandHistoryMetadata
 *
 * @package Andrewlamers\LaravelAdvancedConsole\Models
 * @property string $caller_path
 * @property string $caller_environment
 * @property string $caller_hostname
 * @property string $caller_uid
 * @property string $caller_gid
 * @property int $caller_inode
 * @property string $git_branch
 * @property string $git_commit
 * @property string $git_commit_date
 * @property string $exception_trace
 * @property Command $command
 */

class CommandHistoryMetadata extends Model
{
    protected $table = 'command_history_metadata';
    protected $fillable = ['caller_path', 'caller_environment', 'caller_hostname',
        'caller_uid', 'caller_gid', 'caller_inode', 'git_branch', 'git_commit', 'git_commit_date', 'exception_trace'
    ];

    public function command() {
        return $this->belongsTo(CommandHistory::class);
    }
}
