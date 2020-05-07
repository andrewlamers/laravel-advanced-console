<?php
namespace Andrewlamers\LaravelAdvancedConsole\Events;
use Andrewlamers\LaravelAdvancedConsole\Models\CommandHistory;
use Illuminate\Queue\SerializesModels;

class CommandEvent {
    use SerializesModels;

    public $commandHistory;

    public function __construct(CommandHistory $commandHistory)
    {
        $this->commandHistory = $commandHistory;
    }

}