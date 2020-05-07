<?php
namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Events\CommandCompleteEvent;
use Andrewlamers\LaravelAdvancedConsole\Events\CommandEvent;
use Andrewlamers\LaravelAdvancedConsole\Events\CommandExecutedEvent;
use Andrewlamers\LaravelAdvancedConsole\Events\CommandExecutingEvent;
use Andrewlamers\LaravelAdvancedConsole\Events\CommandFailedEvent;
use Andrewlamers\LaravelAdvancedConsole\Exceptions\CommandHistoryOutputException;
use Andrewlamers\LaravelAdvancedConsole\Facades\CommandConfig;
use Andrewlamers\LaravelAdvancedConsole\Models\CommandHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;

class CommandEventService extends Service
{
   public function onComplete(): void
   {
       $this->dispatch(CommandCompleteEvent::class);
   }

   public function beforeExecute(): void
   {
       $this->dispatch(CommandExecutingEvent::class);
   }

   public function afterExecute(): void
   {
        $this->dispatch(CommandExecutedEvent::class);
   }
   
   public function onException(): void {
       $this->dispatch(CommandFailedEvent::class);
   }

    protected function dispatch(string $event):void {
       $eventClass = new $event($this->command->commandHistory->getModel());
       Container::getInstance()->make('events')->dispatch($eventClass);
   }
}