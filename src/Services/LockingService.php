<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 11/12/18
 * Time: 10:40 AM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Services;


class LockingService extends Service
{
    public function beforeExecute(): void
    {
        if($this->isEnabled()) {
            $this->command->info('Command locking enabled, attempting to aquire lock.');

            $running_commands = $this->command->commandHistory->getRunningProcesses();

            if($running_commands->count() > 0) {
                $tries = 0;
                while ($running_commands->count() > 0 && $tries <= 3) {
                    if ($tries >= 3) {
                        $this->command->error('Unable to acquire lock. Exiting command.');
                        $this->command->disable();
                        return;
                    }

                    $this->command->warn('Unable to acquire lock, trying for 30 seconds to attempt locking again.');
                    $tries++;
                    sleep(1);
                    $running_commands = $this->command->commandHistory->getRunningProcesses();
                }
            }

            $this->command->info('Lock acquired, executing command.');
        }

        $this->command->enable();
    }
}