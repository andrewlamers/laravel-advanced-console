<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 3/2/18
 * Time: 2:34 PM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Command;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class Service
{
    /**
     * @var Command $command
     */
    protected $command;
    protected $application;
    protected $enabled = false;
    protected $canDisable = true;

    /**
     * Service constructor.
     *
     * @param Command $command
     */

    public function __construct($command = null) {
        if($command) {
            $this->setCommand($command);
        }
    }

    public function configure(): void {
        if($this->isEnabled()) {
            $this->command->addOption($this->getDisableOptionName(),
                NULL, NULL,
                'Disable ' . $this->getServiceName() . ' service.');
        } else {
            $this->command->addOption($this->getEnableOptionName(),
                null, null,
                'Enable ' . $this->getServiceName() . ' service.');
        }
    }

    public function initialize($input, $output): void {
        if($this->isDisabled() && $this->canDisable()) {
            $this->disable();
        }
    }

    protected function getDisableOptionName(): string {
        return strtolower('disable-' . Str::snake($this->getServiceName(), '-'));
    }

    protected function getEnableOptionName(): string {
        return strtolower('enable-' . Str::snake($this->getServiceName(), '-'));
    }

    protected function canDisable(): bool {
        return $this->canDisable;
    }

    public function isDisabled(): bool {

        if($this->option($this->getDisableOptionName())) {
            return true;
        }

        return false;
    }

    protected function option($name) {
        return $this->command->option($name);
    }

    /**
     * @param Command $command
     */
    public function setCommand(Command $command): void {
        $this->command = $command;
        $this->setApplication($this->command->getLaravel());
    }

    /**
     * @param Application $application
     */
    protected function setApplication(Application $application): void {
        $this->application = $application;
    }

    /**
     * @param      $command
     * @param null $path
     * @return string
     */
    protected function runProcess($command, $path = null): string {
        $matches = [];

        preg_match_all('/\{(?P<function>[a-zA-Z]+)\}/i', $command, $matches);

        if((count($matches) > 0) && isset($matches['function']) && count($matches['function']) > 0) {
            foreach($matches['function'] as $function) {
                if(method_exists($this, $function)) {
                    $result = $this->$function();
                    if(is_string($result) || is_int($result)) {
                        $command = str_replace('{' . $function . '}', $result, $command);
                    }
                }
            }
        }

        if($path === null) {
            $path = $this->getPath();
        }

        $output = null;
        $process = new Process([$command], $path);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        if($process->isTerminated()) {
            return 'fatal: ' . trim($process->getErrorOutput());
        }

        throw new ProcessFailedException($process);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function config($key) {
        return $this->application['config']->get($key);
    }

    public function getServiceClass(): string {
        return class_basename($this);
    }

    public function getServiceName(): string {
        $class = $this->getServiceClass();
        $base = str_replace("Service", '', $class);
        return ucfirst($base);
    }

    public function getEnableVariableName(): string {
        return sprintf('enable%s', $this->getServiceName());
    }

    public function setEnabled($enabled = true): void {
        $name = $this->getEnableVariableName();

        if(isset($this->command->{$name})) {
            $this->command->{$name} = $enabled;
        }
    }

    public function enable(): void {
        $this->setEnabled();
    }

    public function disable(): void {
        $this->setEnabled(false);
    }

    public function isEnabled() {

        $name = $this->getEnableVariableName();

        if(isset($this->command->{$name})) {
            return $this->command->{$name};
        }

        return false;
    }

    protected function getPath() {
        return $this->application->basePath();
    }

    protected function getHost(): string {
        $hostname = gethostname();
        $ip = gethostbyname($hostname);
        return sprintf('%s (%s)', $hostname, $ip);
    }

    protected function getPhpVersion(): string {
        return PHP_VERSION;
    }

    protected function getEnvironment(): string {
        return $this->command->getLaravel()->environment();
    }

    protected function getUser(): string {
        return get_current_user();
    }

    protected function getPID(): int {
        return getmypid();
    }

    protected function getUID(): int {
        return getmyuid();
    }

    public function getGID(): int {
        return getmygid();
    }

    public function getInode(): int {
        return getmyinode();
    }

    public function isGitRepo(): bool {
        $result = $this->runProcess('git status');

        if(Str::contains($result, 'fatal')) {
            return false;
        }

        return true;
    }

    public function getGitBranch(): string {
        return $this->runProcess('git rev-parse --abbrev-ref HEAD');
    }

    public function getGitCommitHash(): string {
        return $this->runProcess('git log --pretty="%H" -n1 HEAD');
    }

    public function getGitCommitDate(): ?string {
        try {
            return Carbon::parse($this->runProcess('git log --pretty="%ci" -n1 HEAD'))->tz('utc')->format('Y-m-d H:i:s');
        } catch(Exception $e) {
            return null;
        }
    }

    public function getGitCommitterName(): string {
        return $this->runProcess('git log --pretty="%an" -n1 HEAD');
    }

    public function getGitCommitterEmail(): string {
        return $this->runProcess('git log --pretty="%ae" -n1 HEAD');
    }

    public function getGitCommitMessage(): string {
        return $this->runProcess('git log --pretty="%s" -n1 HEAD');
    }

    public function getAppDebug(): string {
        return (Container::getInstance()->make('config')->get('app.debug') ? 'True' : 'False');
    }

    public function onWrite($line) {}
    public function onComplete(): void {}
    public function onLoad(): void {}
    public function beforeRun(): void {}
    public function afterRun(): void {}
    public function beforeExecute(): void {}
    public function afterExecute(): void {}
    public function onShutdown(): void {}
}